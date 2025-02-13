<?php

namespace YonisSavary\Cube\Console\Commands\Migrate;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Console\Commands\Model\Generate;
use YonisSavary\Cube\Database\MigrationManager;

class Migrate extends Command
{
    public function getScope(): string
    {
        return "migrate";
    }

    public function execute(Args $args): int
    {
        $manager = MigrationManager::getInstance();
        $manager->executeAllMigrations();

        if (!$args->has("-s", "--skip-generation"))
            return Generate::call();

        return 0;
    }
}