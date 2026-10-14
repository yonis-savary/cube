<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Env\Logger\NullLogger;
use Cube\Env\Logger\StdOutLogger;
use Cube\Queue\Queue as CubeQueue;
use Exception;

class Queue extends Command
{
    public function getScope(): string
    {
        return "cube";
    }

    public function getHelp(): string
    {
        return "Launch/Flush a given Queue";
    }

    /**
     * @return class-string<CubeQueue>
     */
    protected function findQueueClass(string $target): string
    {
        $candidates = $this->classesNamed(CubeQueue::class, $target);

        return match ($candidates->count()) {
            0 => throw new Exception("Could not find a queue class named [$target]"),
            1 => $candidates->first(),
            default => throw new Exception("[$target] matches several queue classes, give one of them : " . $candidates->join(', ')),
        };
    }

    public function execute(Args $args): int
    {
        /** @var class-string<CubeQueue> $queueClass */
        $queueClass = $args->getValue('q', 'queue');
        if (!class_exists($queueClass))
            $queueClass = $this->findQueueClass($queueClass);

        if (!class_exists($queueClass))
            throw new Exception("$queueClass class does not exists");

        if (!Autoloader::extends($queueClass, CubeQueue::class))
            throw new Exception("$queueClass is not a queue class");


        $logger = $args->has("-l", "--log")
            ? new StdOutLogger()
            : new NullLogger()
        ;

        $instance = Injector::getInstance()->instanciate($queueClass);

        if ($args->has("-f", "--flush")) {
            $logger->info("Flushing " . $queueClass . " queue");
            $instance->flush();
            return 0;
        }

        $instance->loop($logger);
        return 0;
    }
}