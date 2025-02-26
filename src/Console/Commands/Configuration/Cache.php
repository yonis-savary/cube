<?php

namespace Cube\Console\Commands\Configuration;

use Cube\Configuration\Configuration;
use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;
use Cube\Utils\Path;

class Cache extends Command
{
    public function getScope(): string
    {
        return 'configuration';
    }

    public function getHelp(): string
    {
        return 'Cache your configuration file for better performances';
    }

    public function execute(Args $args): int
    {
        try {
            $config = Configuration::getInstance();
            $storage = $config->putToCache();

            $directory = Path::toRelative($storage->getRoot());
            Console::log(Console::withGreenColor("Cache file written to [{$directory}]"));

            return 0;
        } catch (\Throwable $e) {
            Console::log(Console::withRedBackground('Could not cache current configuration'));
            Console::log(Console::withRedBackground($e::class));
            Console::log(Console::withRedBackground($e->getMessage()));

            return 1;
        }
    }
}
