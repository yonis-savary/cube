<?php

namespace Cube\Tests\Units\Queue\Classes;

use Cube\Data\Database\Database;
use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Queue;

/**
 * Loses its database connection on a first item, then queries the database on the second and stops.
 */
class DatabaseWorkerQueue extends Queue
{
    public array $answers = [];

    public function __construct(
        protected \Closure $loseTheConnection
    ) {}

    public function getLogger(): Logger
    {
        return new NullLogger();
    }

    public function __invoke(string $step)
    {
        if ('lose-the-connection' === $step) {
            ($this->loseTheConnection)();
            // Longer than the one-second keep-alive interval of the test, so the loop checks before the next item
            usleep(1_100_000);

            return;
        }

        try {
            $this->answers[] = Database::getInstance()->query('SELECT 1 AS one')[0]['one'];
        } finally {
            $this->stop();
        }
    }

    protected function getDriver(): QueueDriver
    {
        return new LocalDiskQueueDriver();
    }
}
