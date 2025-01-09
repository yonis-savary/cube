<?php

namespace YonisSavary\Cube\Console\Commands\Configuration;

use Throwable;
use YonisSavary\Cube\Configuration\Configuration;
use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Utils\Console;
use YonisSavary\Cube\Utils\Path;

class Cache extends Command
{
    public function getScope(): string
    {
        return "configuration";
    }

    public function execute(Args $args): int
    {
        try
        {
            $config = Configuration::getInstance();
            $storage = $config->putToCache();

            $directory = Path::toRelative($storage->getRoot());
            Console::log(Console::withGreenColor("Cache file written to [$directory]"));

            return 0;
        }
        catch (Throwable $e)
        {
            Console::log(Console::withRedBackground("Could not cache current configuration"));
            Console::log(Console::withRedBackground($e::class));
            Console::log(Console::withRedBackground($e->getMessage()));

            return 1;
        }
    }
}