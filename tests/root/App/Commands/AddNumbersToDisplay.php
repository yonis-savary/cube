<?php

namespace App\Commands;

use App\Queue\DisplayerQueue;
use Cube\Console\Args;
use Cube\Console\Command;

class AddNumbersToDisplay extends Command
{
    public function execute(Args $args): int
    {
        for ($i=0; $i<100; $i++) {
            DisplayerQueue::addToDisplay($i);
        }
        return 0;
    }
}