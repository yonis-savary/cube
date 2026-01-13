<?php

namespace Cube\Console\Commands\Make;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\Database\MigrationManager;
use Cube\Env\Storage;
use Cube\Utils\Console;

class Migration extends Command
{
    public function getScope(): string
    {
        return 'make';
    }

    public function getHelp(): string
    {
        return "Given a classname, create a database migration file (eg: php do make:migration AddUserTable)";
    }

    public function execute(Args $args): int
    {
        $migrationName = $args->getValue() ?? readline('Migration name ? ');

        $migrationManager = MigrationManager::getDefaultInstance();

        $directoryName = $migrationManager->configuration->directoryName;
        $application = new Storage(Console::chooseApplication());

        $writtenFile = $migrationManager->createMigration(
            $migrationName,
            $application->child($directoryName)
        );

        Console::log(
            Console::withGreenBackground("Written file {$writtenFile}")
        );

        return 0;
    }
}
