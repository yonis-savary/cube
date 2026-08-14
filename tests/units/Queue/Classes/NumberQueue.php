<?php

namespace Cube\Tests\Units\Queue\Classes;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Queue;

class NumberQueue extends Queue
{
    public array $numbers = [];

    public function __construct(
        protected QueueDriver $outerDriver
    ) {}

    public function getLogger(): Logger
    {
        return new NullLogger();
    }

    public function __invoke(int ...$numbers)
    {
        array_push($this->numbers, ...$numbers);
    }

    protected function getDriver(): QueueDriver
    {
        return $this->outerDriver;
    }
}
