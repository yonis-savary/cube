<?php

namespace YonisSavary\Cube\Console\Commands\Routine;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Routine\AbstractQueue;
use YonisSavary\Cube\Routine\Scheduler;
use YonisSavary\Cube\Utils\Console;

class Launch extends Command
{
    public function getScope(): string
    {
        return "routine";
    }

    public function getHelp(): string
    {
        return "Launch queues and scheduled tasks that should launch at that time";
    }

    protected function launchQueues(Logger $logger): void
    {
        Logger::withInstance($logger, function() {

            Console::print("Processing queues handlers...");

            $toLaunch = Bunch::of(Autoloader::classesThatExtends(AbstractQueue::class))
            ->map(fn($x) => new $x)
            ->filter(fn(AbstractQueue $queue) => $queue::shouldLaunch())
            ->get();

            Console::withProgressBar($toLaunch, function(AbstractQueue $queue) {
                $count = $queue::batchSize();
                $trueCount = max($queue::countToProcess(), $count);

                Console::print("- Processing $trueCount item for " . $queue::class);
                for ($i=0; $i<$count; $i++)
                    $queue::processNext();
            });
        });
    }

    protected function launchScheduler(Logger $logger): void
    {
        Logger::withInstance($logger, function() {
            Console::print("Launching Scheduler");
            Scheduler::getInstance()->launch();
        });
    }

    public function execute(Args $args): int
    {
        $logger = new Logger("routine-launches.csv");
        $logger->info("Watching routine command on " . date("Y-m-d H:i:s"));

        $contextLogger = new Logger("routine.csv");
        $this->launchQueues($contextLogger);
        $this->launchScheduler($contextLogger);

        return 0;
    }
}