<?php

namespace Cube\Tests\Units\Console\Classes;

use Cube\Console\Args;
use Cube\Console\Command;

class SayHello extends Command
{
    public static int $executions = 0;
    public static ?Args $lastArgs = null;

    public function execute(Args $args): int
    {
        ++self::$executions;
        self::$lastArgs = $args;

        return $args->has('-f', '--fail') ? 1 : 0;
    }
}
