<?php

namespace App\Queue;

use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Queue;

class DisplayerQueue extends Queue
{
    public function getDriver(): ?QueueDriver
    {
        return new LocalDiskQueueDriver(static::class);
    }

    public static function display($args) {
        self::getInstance()->info(print_r($args, true));
    }

    public static function addToDisplay(...$args) {
        self::getInstance()->push([self::class, 'display'], $args);
    }
}