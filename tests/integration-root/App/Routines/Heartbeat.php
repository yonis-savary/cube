<?php

namespace App\Routines;

use Cube\Env\Logger\Logger;

class Heartbeat
{
    public static function ping(): void
    {
        Logger::getInstance()->info('Heartbeat ping');
    }
}
