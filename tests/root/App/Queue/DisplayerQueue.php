<?php

namespace App\Queue;

use Cube\Queue\LocalDiskQueue;
use Cube\Queue\Queue;
use Cube\Queue\QueueDriver;

class DisplayerQueue extends Queue
{
    public function getDriver(): ?QueueDriver
    {
        return new LocalDiskQueue(static::class);
    }

    public static function display($args) {
        self::getInstance()->info(print_r($args, true));
    }

    public static function addToDisplay(...$args) {
        self::getInstance()->push([self::class, 'display'], $args);
    }
}