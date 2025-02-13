<?php

namespace YonisSavary\Cube\Database;

use RuntimeException;
use Throwable;
use YonisSavary\Cube\Core\Autoloader\Applications;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Database\Migration\Adapters\MySQL;
use YonisSavary\Cube\Database\Migration\Adapters\SQLite;
use YonisSavary\Cube\Database\Migration\Migration;
use YonisSavary\Cube\Database\Migration\MigrationManagerConfiguration;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Utils\Text;

abstract class MigrationManager
{
    use Component;

    protected Database $database;
    protected Bunch $migrationFiles;

    protected ?Throwable $lastError = null;
    protected ?string $lastErrorFile = null;

    public readonly MigrationManagerConfiguration $configuration;

    public function __construct(
        Database $database,
        ?MigrationManagerConfiguration $configuration=null
    )
    {
        $this->database = $database;

        $this->configuration ??= $configuration ?? MigrationManagerConfiguration::resolve();

        $this->migrationFiles =
            Bunch::of(Applications::resolve()->paths)
            ->map(fn(string $app) => new Storage($app))
            ->map(fn(Storage $app) => $app->child($this->configuration->directoryName))
            ->map(fn(Storage $migrationDirectory) => $migrationDirectory->files())
            ->flat()
            ->sort(fn($file) => basename($file));

        $this->createMigrationTableIfInexistant();
    }

    public function getMigrationTableName(): string
    {
        return $this->configuration->tableName;
    }

    public function getLastError(): ?Throwable
    {
        return $this->lastError;
    }

    public function getLastErrorFile(): ?string
    {
        return $this->lastErrorFile;
    }

    public abstract function migrationWasMade(string $name): bool;

    public abstract function createMigrationTableIfInexistant();

    public abstract function markMigrationAsDone(string $name);

    public abstract function listDoneMigrations(): array;

    protected abstract function startTransaction();

    protected abstract function commitTransaction();

    protected abstract function rollbackTransaction();

    public function executeMigration(string $file): bool
    {
        $migrationName = basename($file);

        if ($this->migrationWasMade($migrationName))
            return true;

        try
        {
            $this->startTransaction();

            /** @var Migration $migration */
            $migration = include $file;

            $sqlContent = $migration->install;
            if (!trim($sqlContent))
                Logger::getInstance()->warning("Skipping empty migration $file");
            else
                $this->database->exec($sqlContent);

            $this->markMigrationAsDone($migrationName);
            $this->commitTransaction();
            return true;
        }
        catch (Throwable $thrown)
        {
            $this->rollbackTransaction();
            $this->lastError  = $thrown;
            $this->lastErrorFile = $file;
            Logger::getInstance()->error("Failed migration $file");
            Logger::getInstance()->logThrowable($thrown);
            return false;
        }
    }

    public function executeAllMigrations(): bool
    {
        foreach ($this->migrationFiles->toArray() as $file)
            $this->executeMigration($file);

        return true;
    }

    public function createMigration(string $name, Storage $directory): string
    {
        $filename = date("Y_m_d_h_i_s_") . $name .".php";
        $directory->write($filename, Text::toFile("
        <?php

        use ".Migration::class.";

        return new Migration(
            \"-- INSTALL SCRIPT
        \",
            \"-- UNINSTALL SCRIPT
        \");
        "));

        return $directory->path($filename);
    }

    public function migrationExists(string $name): bool
    {
        return $this->migrationFiles->first(
            fn($file) => basename($file) === $name
        ) !== null;
    }

    public static function getDefaultInstance(): static
    {
        $database = Database::getInstance();
        $databaseDriver = $database->getDriver();

        switch (strtolower($databaseDriver))
        {
            case "mysql":
                return new MySQL($database);
                break;
            case "sqlite":
                return new SQLite($database);
                break;
        }
        throw new RuntimeException("No migration driver found for [$databaseDriver] database");
    }

    public function catchUpTo(string $name): array
    {
        $doneMigrations = [];

        foreach ($this->migrationFiles->toArray() as $file)
        {
            $doneMigrations[] = $file;

            $thisFileName = basename($file);
            if (!$this->migrationWasMade($thisFileName))
                $this->markMigrationAsDone($thisFileName);

            if ($file === $name)
                break;
        }
        return $doneMigrations;
    }
}