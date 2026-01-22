<?php

namespace Cube\Console\Commands\Migration;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Console\Commands\Model\Generate;
use Cube\Data\Database\Migration\FailedMigrationException;
use Cube\Data\Database\MigrationManager;
use Cube\Utils\Console;

class Migrate extends Command
{
    public function getScope(): string
    {
        return 'migrate';
    }

    public function getHelp(): string
    {
        return "Execute needed migration in your database";
    }

    public function execute(Args $args): int
    {
        $manager = MigrationManager::getInstance();
        $manager->setLoggingFunction(fn($m) => print($m . "\n"));
        $manager->executeAllMigrations();

        if (!$args->has('-s', '--skip-generation')) {
            Console::print("");
            return Generate::call();
        }

        return 0;
    }
}
