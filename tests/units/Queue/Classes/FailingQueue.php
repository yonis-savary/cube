<?php

namespace Cube\Tests\Units\Queue\Classes;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Queue;

class FailingQueue extends Queue
{
    public int $attempts = 0;
    public bool $repushOnError = false;

    public function __construct(
        protected QueueDriver $outerDriver
    ) {}

    public function getLogger(): Logger
    {
        return new NullLogger();
    }

    public function __invoke(mixed $value)
    {
        ++$this->attempts;

        throw new \RuntimeException("Could not process [{$value}]");
    }

    protected function getDriver(): QueueDriver
    {
        return $this->outerDriver;
    }

    protected function onError(\Throwable $thrown, array $args): bool
    {
        return $this->repushOnError;
    }
}
