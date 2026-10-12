<?php

namespace Cube\Console\Commands\Key;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;

class Generate extends Command
{
    public function getHelp(): string
    {
        return 'Print a random key to put in your .env as APP_KEY';
    }

    public function getScope(): string
    {
        return 'key';
    }

    public function execute(Args $args): int
    {
        Console::log('APP_KEY='.self::newKey());

        return 0;
    }

    public static function newKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
