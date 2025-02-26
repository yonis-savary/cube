<?php

namespace Cube\Console\Commands\Migrate;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Console\Commands\Model\Generate;
use Cube\Database\MigrationManager;

class Migrate extends Command
{
    public function getScope(): string
    {
        return 'migrate';
    }

    public function execute(Args $args): int
    {
        $manager = MigrationManager::getInstance();
        $manager->executeAllMigrations();

        if (!$args->has('-s', '--skip-generation')) {
            return Generate::call();
        }

        return 0;
    }
}
