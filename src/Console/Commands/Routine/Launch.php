<?php

namespace Cube\Console\Commands\Routine;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Logger\Logger;
use Cube\Routine\Queue;
use Cube\Routine\Scheduler;
use Cube\Utils\Console;

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

            $toLaunch = Bunch::of(Autoloader::classesThatExtends(Queue::class))
            ->map(fn($x) => new $x)
            ->filter(fn(Queue $queue) => $queue::shouldLaunch())
            ->get();

            Console::withProgressBar($toLaunch, function(Queue $queue) {
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