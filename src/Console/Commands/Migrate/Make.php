<?php

namespace YonisSavary\Cube\Console\Commands\Migrate;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Database\MigrationManager;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Utils\Console;
use YonisSavary\Cube\Utils\Shell;

class Make extends Command
{
    public function getScope(): string
    {
        return "migrate";
    }

    public function execute(Args $args): int
    {
        $migrationName = $args->getValue() ?? readline("Migration name ? ");

        $migrationManager = MigrationManager::getDefaultInstance();

        $directoryName = $migrationManager->configuration->directoryName;
        $application = new Storage(Console::chooseApplication());

        $writtenFile = $migrationManager->createMigration(
            $migrationName,
            $application->child($directoryName)
        );

        Console::log(
            Console::withGreenBackground("Written file $writtenFile")
        );

        return 0;
    }
}