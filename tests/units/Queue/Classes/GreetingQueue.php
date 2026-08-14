<?php

namespace Cube\Tests\Units\Queue\Classes;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Queue;

/**
 * Left on the default local disk driver, so it can be reached through the static
 * `Queue::queue()` entry point, which builds the queue through the Injector.
 */
class GreetingQueue extends Queue
{
    public static array $greeted = [];

    public function getLogger(): Logger
    {
        return new NullLogger();
    }

    public function __invoke(string $name)
    {
        self::$greeted[] = $name;
    }
}
