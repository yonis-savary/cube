<?php

namespace Cube\Console\Commands\Migrate;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Console\Commands\Model\Generate;
use Cube\Data\Database\MigrationManager;
use Cube\Utils\Console;

class Migrate extends Command
{
    public function getScope(): string
    {
        return 'migrate';
    }

    public function execute(Args $args): int
    {
        $manager = MigrationManager::getInstance();
        $manager->setLoggingFunction(fn($m) => print($m . "\n"));
        $migrated = $manager->executeAllMigrations();

        if ($migrated && !$args->has('-s', '--skip-generation')) {
            Console::print("");
            return Generate::call();
        }

        return 0;
    }
}
