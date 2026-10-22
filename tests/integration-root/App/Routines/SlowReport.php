<?php

namespace App\Routines;

use Cube\Env\Logger\Logger;

class SlowReport
{
    public function __invoke(Logger $logger): void
    {
        $logger->info('SlowReport started');
        sleep(2);
        $logger->info('SlowReport finished');
    }
}
