<?php

namespace App\Queue;

use Cube\Queue\Queue;

class DisplayerQueue extends Queue
{
    public function __invoke($args)
    {
        $this->logger->info(print_r($args, true));
    }
}