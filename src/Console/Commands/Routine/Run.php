<?php

namespace Cube\Console\Commands\Routine;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Env\Logger\Logger;
use Cube\Routine\Scheduler;

class Run extends Command
{
    public function getScope(): string
    {
        return 'routine';
    }

    public function getHelp(): string
    {
        return 'Run one scheduled task, skipped while its previous run holds the lock (routine:launch calls it)';
    }

    public function getManual(): string|array
    {
        return [
            'routine:run <class> [method] [--without-lock]',
            '<class> [method] : App\\Reports sendWeekly, or App\\ArchiveInvoices alone for an invokable class',
            '--without-lock : run even while a previous run of the task is still running',
        ];
    }

    public function execute(Args $args): int
    {
        [$class, $method] = $args->getValues() + [null, null];
        if (!$class)
            return $this->abort('Give the task to run, for example : php do routine:run "App\\Reports" sendWeekly');

        $task = $method ? [$class, $method] : $class;

        $lock = !$args->has(long: 'without-lock');
        $logger = Logger::forFile('launch.csv');

        return $logger->asGlobalInstance(function () use ($task, $lock, $logger) {
            try {
                Scheduler::getInstance()->run($task, $lock);

                return 0;
            } catch (\Throwable $thrown) {
                $logger->logThrowable($thrown);

                return 1;
            }
        });
    }
}
