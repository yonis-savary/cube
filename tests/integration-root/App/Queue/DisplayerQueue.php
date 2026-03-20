<?php

namespace App\Queue;

use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Queue;

class DisplayerQueue extends Queue
{
    protected function getDriver(): QueueDriver
    {
        return new LocalDiskQueueDriver();
    }

    public function __invoke(mixed $toDisplay)
    {
        $this->logger->info("DISPLAY : ". print_r($toDisplay, true));
    }
}