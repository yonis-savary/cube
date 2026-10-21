<?php

namespace Cube\Data\Database;

use Cube\Core\Autoloader\Applications;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Data\Database\Migration\FailedMigrationException;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;
use Cube\Env\Storage;
use Cube\Env\Logger\Logger;
use Cube\Utils\Console;
use Cube\Utils\Text;
use DateTime;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MigrationManager
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
            = Bunch::wrap(Applications::resolve()->paths)
                ->map(fn (string $app) => new Storage($app))
                ->map(fn (Storage $app) => $app->child($this->configuration->directoryName))
                ->map(fn (Storage $migrationDirectory) => $migrationDirectory->files())
                ->flat()
                ->filter(fn (string $file) => str_ends_with($file, '.php'))
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

    public function migrationWasMade(string $name): bool
    {
        return Query::select($this->getMigrationTableName())
            ->selectField('name')
            ->where('name', $name)
            ->exists($this->database);
    }

    public function createMigrationTableIfInexistant(): void
    {
        $table = $this->getMigrationTableName();
        if ($this->database->hasTable($table))
            return;

        $this->getPlan()->create($table, [
            ModelField::id(),
            ModelField::string('name', 200)->notNull()->unique(),
            ModelField::datetime('created_at')->notNull(),
        ]);
    }

    public function markMigrationAsDone(string $name): void
    {
        Query::insert($this->getMigrationTableName())
            ->insertField(['name', 'created_at'])
            ->values([$name, new DateTime()])
            ->fetch($this->database);
    }

    public function markMigrationAsUndone(string $name): void
    {
        Query::delete($this->getMigrationTableName())
            ->where('name', $name)
            ->fetch($this->database);
    }

    /** @return string[] */
    public function listDoneMigrations(): array
    {
        return Bunch::wrap(
            Query::select($this->getMigrationTableName())
                ->selectField('name')
                ->order('id', 'ASC')
                ->fetch($this->database)
        )
            ->map(fn ($row) => $row->name)
            ->toArray();
    }

    public function executeMigration(string $file): bool
    {
        $this->createMigrationTableIfInexistant();

        $migrationName = basename($file);

        if ($this->migrationWasMade($migrationName)) {
            return true;
        }

        /** @var Migration $migration */
        $migration = include $file;

        if ($error = $migration->execute($this->getPlan(), $this->database))
            return $this->recordFailure($file, $error, "Error with $file ! ");

        $this->markMigrationAsDone($migrationName);
        $this->log(Console::withGreenColor("Migration: $file applied"));

        return true;
    }

    public function revertMigration(string $file): bool
    {
        /** @var Migration $migration */
        $migration = include $file;

        if ($error = $migration->revert($this->getPlan(), $this->database))
            return $this->recordFailure($file, $error, "Error while rolling back $file ! ");

        $this->markMigrationAsUndone(basename($file));
        $this->log(Console::withGreenColor("Migration: $file rolled back"));

        return true;
    }

    public function rollback(int $steps = 1): void
    {
        if ($steps < 1)
            throw new InvalidArgumentException("Cannot rollback [{$steps}] migrations, expected at least 1");

        $this->createMigrationTableIfInexistant();

        $names = array_slice(array_reverse($this->listDoneMigrations()), 0, $steps);

        if (!count($names)) {
            $this->log(Console::withBlueColor("Nothing to rollback !"));
            return;
        }

        foreach ($names as $name) {
            if (!$this->revertMigration($this->getMigrationFile($name)))
                throw new FailedMigrationException($this->lastError, $this->lastErrorFile);
        }
    }

    protected function getMigrationFile(string $name): string
    {
        return $this->migrationFiles->first(fn ($file) => basename($file) === $name)
            ?? throw new RuntimeException("Cannot find the file of the applied migration [{$name}] in any [{$this->configuration->directoryName}] directory");
    }

    protected function getPlan(): Plan
    {
        return Plan::forDatabase($this->database);
    }

    protected function recordFailure(string $file, Throwable $error, string $message): false
    {
        $this->log(Console::withRedColor($message . $error->getMessage()));
        $this->lastError = $error;
        $this->lastErrorFile = $file;
        Logger::getInstance()->error('Failed migration {file}', ['file' => $file]);
        Logger::getInstance()->logThrowable($error);

        return false;
    }

    /**
     * @return bool `true` if one migration was executed, `false` otherwise
     */
    public function executeAllMigrations()
    {
        $this->createMigrationTableIfInexistant();

        $files = $this->migrationFiles
            ->filter(fn($file) => !$this->migrationWasMade(basename($file)))
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
        $filename = date('Y_m_d_H_i_s_').$name.'.php';
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
        };
        '));

        return $directory->path($filename);
    }

    public function migrationExists(string $name): bool
    {
        return null !== $this->migrationFiles->first(
            fn ($file) => basename($file) === $name
        );
    }
}
