<?php

namespace Cube\Data\Database;

use Cube\Core\Autoloader\Applications;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Data\Database\Migration\Adapters\MySQL;
use Cube\Data\Database\Migration\Adapters\SQLite;
use Cube\Data\Database\Migration\Adapters\Postgres;
use Cube\Env\Storage;
use Cube\Env\Logger\Logger;
use Cube\Utils\Console;
use Cube\Utils\Text;

abstract class MigrationManager
{
    use Component;

    public readonly MigrationManagerConfiguration $configuration;

    protected Database $database;
    protected Bunch $migrationFiles;

    protected ?\Throwable $lastError = null;
    protected ?string $lastErrorFile = null;

    protected $logFunction = null;

    public function __construct(
        Database $database,
        ?MigrationManagerConfiguration $configuration = null
    ) {
        $this->database = $database;

        $this->configuration = $configuration ?? MigrationManagerConfiguration::resolve();

        $this->migrationFiles
            = Bunch::of(Applications::resolve()->paths)
                ->map(fn (string $app) => new Storage($app))
                ->map(fn (Storage $app) => $app->child($this->configuration->directoryName))
                ->map(fn (Storage $migrationDirectory) => $migrationDirectory->files())
                ->flat()
                ->sort(fn ($file) => basename($file))
        ;

        $this->createMigrationTableIfInexistant();
    }

    public function setLoggingFunction(callable $function) {
        $this->logFunction = $function;
    }

    protected function log(string $message): void {
        if ($this->logFunction)
            ($this->logFunction)($message);
    }

    public function getMigrationTableName(): string
    {
        return $this->configuration->tableName;
    }

    public function getLastError(): ?\Throwable
    {
        return $this->lastError;
    }

    public function getLastErrorFile(): ?string
    {
        return $this->lastErrorFile;
    }

    abstract public function migrationWasMade(string $name): bool;

    abstract public function createMigrationTableIfInexistant();

    abstract public function markMigrationAsDone(string $name);

    abstract public function listDoneMigrations(): array;

    public function executeMigration(string $file): bool
    {
        $migrationName = basename($file);

        if ($this->migrationWasMade($migrationName)) {
            return true;
        }

        try {
            /** @var Migration $migration */
            $migration = include $file;

            $sqlContent = $migration->install;
            if (!trim($sqlContent)) {
                Logger::getInstance()->warning("Skipping empty migration {$file}");
            } else {
                $this->log(Console::withGreenColor("Start migration $file..."));
                $this->startTransaction();
                $this->database->exec($sqlContent);
                $this->markMigrationAsDone($migrationName);
            }

            $this->commitTransaction();

            return true;
        } catch (\Throwable $thrown) {
            $this->log(Console::withRedColor("Error with $file ! " . $thrown->getMessage()));
            $this->rollbackTransaction();
            $this->lastError = $thrown;
            $this->lastErrorFile = $file;
            Logger::getInstance()->error("Failed migration {$file}");
            Logger::getInstance()->logThrowable($thrown);

            return false;
        }
    }

    /**
     * @return bool `true` if one migration was executed, `false` otherwise
     */
    public function executeAllMigrations(): bool
    {
        $files = Bunch::of($this->migrationFiles->toArray())
            ->filter(fn($file) => basename($file))
            ->filter(fn($migrationName) => $this->migrationWasMade($migrationName))
            ->toArray();

        if (count($files))
        {
            foreach ($files as $file)
                $this->executeMigration($file);
        }
        else
        {
            $this->log(Console::withBlueColor("Nothing to migrate !"));
            return false;
        }

        return true;
    }

    public function createMigration(string $name, Storage $directory): string
    {
        $filename = date('Y_m_d_h_i_s_').$name.'.php';
        $directory->write($filename, Text::toFile('
        <?php

        use '.Migration::class.';

        return new Migration(
            "-- INSTALL SCRIPT
        ",
            "-- UNINSTALL SCRIPT
        ");
        '));

        return $directory->path($filename);
    }

    public function migrationExists(string $name): bool
    {
        return null !== $this->migrationFiles->first(
            fn ($file) => basename($file) === $name
        );
    }

    public static function getDefaultInstance(): static
    {
        $database = Database::getInstance();
        $databaseDriver = $database->getDriver();

        switch (strtolower($databaseDriver)) {
            case 'mysql':
                return new MySQL($database);
            case 'pgsql':
                return new Postgres($database);
            case 'sqlite':
                return new SQLite($database);
        }

        throw new \RuntimeException("No migration driver found for [{$databaseDriver}] database");
    }

    public function catchUpTo(string $name): array
    {
        $doneMigrations = [];

        foreach ($this->migrationFiles->toArray() as $file) {
            $doneMigrations[] = $file;

            $thisFileName = basename($file);
            if (!$this->migrationWasMade($thisFileName)) {
                $this->markMigrationAsDone($thisFileName);
            }

            if ($file === $name) {
                break;
            }
        }

        return $doneMigrations;
    }

    abstract protected function startTransaction();

    abstract protected function commitTransaction();

    abstract protected function rollbackTransaction();
}
