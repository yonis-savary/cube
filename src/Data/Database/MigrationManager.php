<?php

namespace Cube\Data\Database;

use Cube\Core\Autoloader\Applications;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Data\Database\Migration\FailedMigrationException;
use Cube\Data\Database\Migration\Plan;
use Cube\Env\Storage;
use Cube\Env\Logger\Logger;
use Cube\Utils\Console;
use Cube\Utils\Text;
use RuntimeException;
use Throwable;

abstract class MigrationManager
{
    use Component;

    public readonly MigrationManagerConfiguration $configuration;

    protected Database $database;

    /**
     * @var Bunch<int,string> $migrationFiles
     */
    protected Bunch $migrationFiles;

    protected ?\Throwable $lastError = null;
    protected ?string $lastErrorFile = null;

    protected $logFunction = null;

    public function __construct(
        Database $database,
        MigrationManagerConfiguration $configuration
    ) {
        $this->database = $database;
        $this->configuration = $configuration;

        $this->migrationFiles
            = Bunch::of(Applications::resolve()->paths)
                ->map(fn (string $app) => new Storage($app))
                ->map(fn (Storage $app) => $app->child($this->configuration->directoryName))
                ->map(fn (Storage $migrationDirectory) => $migrationDirectory->files())
                ->flat()
                ->sort(fn ($file) => basename($file))
        ;
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

    abstract public function supports(string $driver): bool;

    public function executeMigration(string $file): bool
    {
        $this->createMigrationTableIfInexistant();

        $migrationName = basename($file);
        $databaseDriver = $this->database->getDriver();

        $plan = Bunch::fromExtends(Plan::class, [$this->database])
            ->first(fn($p) => $p->support($databaseDriver));

        if (!$plan)
            throw new RuntimeException("Could not find any Plan class for database of type $databaseDriver");

        if ($this->migrationWasMade($migrationName)) {
            return true;
        }

        $error = $this->database->transaction(function() use ($file, $plan, $migrationName) {

            /** @var Migration $migration */
            $migration = include $file;
            $migration->up($plan, $this->database);
            $this->markMigrationAsDone($migrationName);
        });

        if ($error instanceof Throwable) {
            $this->log(Console::withRedColor("Error with $file ! " . $error->getMessage()));
            $this->lastError = $error;
            $this->lastErrorFile = $file;
            Logger::getInstance()->error("Failed migration {$file}");
            Logger::getInstance()->logThrowable($error);
            return false;
        } else {
            $this->log(Console::withGreenColor("Migration: $file applied"));
        }

        return true;
    }

    /**
     * @return bool `true` if one migration was executed, `false` otherwise
     */
    public function executeAllMigrations()
    {
        $this->createMigrationTableIfInexistant();

        $files = $this->migrationFiles
            ->filter(fn($file) => basename($file))
            ->filter(fn($migrationName) => !$this->migrationWasMade($migrationName))
            ->toArray();

        if (count($files))
        {
            foreach ($files as $file) 
            {
                if (!$this->executeMigration($file))
                    throw new FailedMigrationException($this->lastError, $this->lastErrorFile);
            }
        }
        else
        {
            $this->log(Console::withBlueColor("Nothing to migrate !"));
        }
    }

    public function createMigration(string $name, Storage $directory): string
    {
        $filename = date('Y_m_d_h_i_s_').$name.'.php';
        $directory->write($filename, Text::toFile('
        <?php

        use '.Database::class.';
        use '.Migration::class.';
        use '.Plan::class.';

        return new class extends Migration
        {
            public function up(Plan $plan, Database $database) {

            }

            public function down(Plan $plan, Database $database) {

            }
        }
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

        $manager = Bunch::fromExtends(MigrationManager::class)
            ->first(fn($manager) => $manager->supports($databaseDriver));

        if (!$manager)
            throw new \RuntimeException("No migration driver found for [{$databaseDriver}] database");

        return $manager;
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
}
