<?php

namespace Cube\Console\Commands\Routine;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Env\Logger\HasLogger;
use Cube\Env\Logger\Logger;
use Cube\Routine\Scheduler;
use Cube\Utils\Console;

class Launch extends Command
{
    use HasLogger;

    public function getScope(): string
    {
        return 'routine';
    }

    public function getHelp(): string
    {
        return 'Launch queues and scheduled tasks that should launch at that time';
    }

    public function execute(Args $args): int
    {
        $logger = $this->getLogger();
        $logger->info('Launched routine command on '.date('Y-m-d H:i:s'));

        $this->launchScheduler($logger);

        return 0;
    }

    protected function launchScheduler(Logger $logger): void
    {
        $logger->asGlobalInstance(function () {
            Console::print('Launching Scheduler');
            Scheduler::getInstance()->launch();
        });
    }
}
