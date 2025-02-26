<?php

namespace Cube\Console\Commands\Migrate;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Database\MigrationManager;
use Cube\Env\Storage;
use Cube\Utils\Console;

class Make extends Command
{
    public function getScope(): string
    {
        return 'migrate';
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
