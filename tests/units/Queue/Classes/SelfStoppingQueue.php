<?php

namespace Cube\Tests\Units\Queue\Classes;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Queue;

/**
 * Leaves its own loop while processing its first item, either by asking the queue directly
 * or by sending the worker the signal a process manager would.
 */
class SelfStoppingQueue extends Queue
{
    public array $seen = [];
    public bool $stopWithSignal = false;

    public function __construct(
        protected QueueDriver $outerDriver
    ) {}

    public function getLogger(): Logger
    {
        return new NullLogger();
    }

    public function __invoke(mixed $value)
    {
        $this->seen[] = $value;

        if (!$this->stopWithSignal) {
            $this->stop();

            return;
        }

        posix_kill(getmypid(), SIGTERM);
    }

    protected function getDriver(): QueueDriver
    {
        return $this->outerDriver;
    }
}
