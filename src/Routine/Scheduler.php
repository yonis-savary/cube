<?php

namespace Cube\Routine;

use Cube\Core\Component;
use Cube\Core\Injector;
use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Cube\Env\Storage;
use Cube\Utils\Path;
use InvalidArgumentException;
use ReflectionMethod;
use Symfony\Component\Process\Process;

class Scheduler
{
    use Component;

    /** @var array<array{CronExpression,array{class-string,string}|class-string,bool}> */
    protected array $handlers = [];

    /**
     * @param array{class-string,string}|class-string $task `[Class::class, 'method']` or an invokable class
     * @param bool $lock `false` lets a run start while the previous one is still running
     */
    public function add(CronExpression $expression, array|string $task, bool $lock = true): self
    {
        $this->assertIsTask($task);
        $this->handlers[] = [$expression, $task, $lock];

        return $this;
    }

    public function launch(\DateTime|string $datetime = 'now'): void
    {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }

        $processes = Bunch::wrap($this->handlers)
            ->filter(fn ($handler) => $handler[0]->matches($datetime))
            ->map(fn ($handler) => [$handler[1], $this->start($handler[1], $handler[2])])
            ->toArray()
        ;

        foreach ($processes as [$task, $process])
            $this->waitFor($task, $process);
    }

    /**
     * @param array{class-string,string}|class-string $task
     * @return bool `false` when its previous run still holds the lock
     */
    public function run(array|string $task, bool $lock = true): bool
    {
        $this->assertIsTask($task);

        if (!$lock) {
            $this->call($task);

            return true;
        }

        // flock() is tied to the open file : the system releases it when a run dies without unlocking
        $lockFile = fopen($this->getLockPath($task), 'c');
        if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
            Logger::getInstance()->warning('Skipped {task}, its previous run is still running', ['task' => $this->getTaskName($task)]);
            fclose($lockFile);

            return false;
        }

        try {
            $this->call($task);
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }

        return true;
    }

    /** @param array{class-string,string}|class-string $task */
    protected function start(array|string $task, bool $lock): Process
    {
        $command = [PHP_BINARY, 'do', 'routine:run', ...(array) $task];
        if (!$lock)
            $command[] = '--without-lock';

        // Process stops a child after 60 seconds by default, a task takes as long as it needs
        $process = new Process($command, Path::getProjectPath(), timeout: null);
        $process->start();

        return $process;
    }

    /** @param array{class-string,string}|class-string $task */
    protected function waitFor(array|string $task, Process $process): void
    {
        $process->wait(function (string $type, string $output) {
            echo $output;
        });

        if ($process->isSuccessful())
            return;

        Logger::getInstance()->error('Task {task} exited with code {code}', [
            'task' => $this->getTaskName($task),
            'code' => $process->getExitCode(),
        ]);
    }

    /** @param array{class-string,string}|class-string $task */
    protected function call(array|string $task): void
    {
        [$class, $method] = is_array($task) ? $task : [$task, '__invoke'];

        $injector = Injector::getInstance();
        $parameters = $injector->getDependencies([$class, $method]);

        if (is_callable([$class, $method])) {
            $class::$method(...$parameters);

            return;
        }

        $injector->instanciate($class)->$method(...$parameters);
    }

    /** @param array<mixed>|string $task What a schedule file or the command line gives */
    protected function assertIsTask(array|string $task): void
    {
        if (is_array($task) && (!array_is_list($task) || 2 !== count($task) || !is_string($task[0]) || !is_string($task[1])))
            throw new InvalidArgumentException('A scheduled task is [Class::class, \'method\'] or an invokable class name, got '.json_encode($task));

        [$class, $method] = is_array($task) ? $task : [$task, '__invoke'];

        if (!class_exists($class))
            throw new InvalidArgumentException("Cannot schedule [{$class}], no such class");

        if (!method_exists($class, $method) || !(new ReflectionMethod($class, $method))->isPublic())
            throw new InvalidArgumentException("Cannot schedule [{$class}], it has no public {$method}() method");
    }

    /** @param array{class-string,string}|class-string $task */
    protected function getTaskName(array|string $task): string
    {
        return implode('::', (array) $task);
    }

    /** @param array{class-string,string}|class-string $task */
    protected function getLockPath(array|string $task): string
    {
        $name = preg_replace('/[^\w]+/', '-', $this->getTaskName($task));

        return Storage::getInstance()->child('Scheduler')->path("{$name}.lock");
    }
}
