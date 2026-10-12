<?php

namespace Cube\Console\Commands\Cache;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Env\Cache;
use Cube\Utils\Console;
use Cube\Utils\Path;

class Clear extends Command
{
    public function getHelp(): string
    {
        return 'Clear every entry of the cache, whatever its driver';
    }

    public function getScope(): string
    {
        return 'cache';
    }

    public function execute(Args $args): int
    {
        foreach (Cache::getInstance()->clear() as $removed)
            Console::log('Deleted ['.Path::toRelative($removed).']');

        Console::log('', Console::withGreenColor('Cache cleared !'), '');

        return 0;
    }
}
