<?php

namespace Cube\Test;

use Cube\Core\Component;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Data\Database\MigrationManager;
use Cube\Env\Configuration;
use Cube\Env\Storage;
use Cube\Utils\Path;
use Cube\Web\Router\Router;

use function Cube\debug;

class TestContext
{
    use Component;

    protected Storage $storage;
    protected Router $router;
    protected ?Database $database;
    protected Database $emptyApplicationDatabase;
    protected Configuration $configuration;
    protected ?MigrationManager $migrationManager = null;

    public function __construct(
        ?Storage $storage=null,
        ?Router $router=null,
        ?Database $database=null,
        ?Configuration $configuration=null
    )
    {
        $this->storage = $storage ?? Storage::getInstance();
        $this->router = $router ?? Router::getInstance();
        $this->database = $database;

        $this->configuration = $configuration ?? Configuration::getInstance();

        $this->replaceGlobalInstances();
    }

    public function replaceGlobalInstances()
    {
        Storage::setInstance($this->storage);
        Router::setInstance($this->router);
        Configuration::setInstance($this->configuration);

        if ($this->database)
            Database::setInstance($this->database);

        MigrationManager::removeInstance();
        $this->migrationManager = null;
    }

    public static function useTemporaryStorage(?Storage $parent = null): Storage
    {
        $parent ??= Storage::getInstance();

        $storage = $parent->child(uniqid('Tests-'));
        Storage::setInstance($storage);
        register_shutdown_function(fn () => self::removeDirectory($storage->getRoot()));

        return $storage;
    }

    protected static function removeDirectory(string $path): void
    {
        if (!is_dir($path))
            return;

        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $child = Path::join($path, $entry);

            if (is_link($child) || !is_dir($child)) {
                unlink($child);
                continue;
            }

            self::removeDirectory($child);
        }

        rmdir($path);
    }

    public function createEmptyApplicationDatabase(): Database
    {
        $database = new Database('sqlite', uniqid('app-db-'));

        $database->asGlobalInstance(function() use ($database) {
            $config = MigrationManagerConfiguration::resolve(Configuration::getInstance());
            $migrationManager = new MigrationManager($database, $config);
            $migrationManager->setLoggingFunction(fn($buffer) => debug($buffer));
            $migrationManager->executeAllMigrations();
        });

        $this->emptyApplicationDatabase = $database;

        return $database;
    }

    public function useNewEmptyApplicationDatabase() {
        $path = $this->emptyApplicationDatabase->getDatabase();
        $newDatabasePath = Storage::getInstance()->path(uniqid('testdb', true));
        copy($path, $newDatabasePath);

        $newDatabase = new Database('sqlite', $newDatabasePath);
        $this->database = $newDatabase;
        Database::setInstance($this->database);
    }

    public function getStorage(): Storage {
        return $this->storage;
    }

    public function getRouter(): Router {
        return $this->router;
    }

    public function getDatabase(): Database {
        return $this->database ??= Database::getInstance();
    }

    public function getConfiguration(): Configuration {
        return $this->configuration;
    }

    public function getMigrationManager(): MigrationManager {
        return $this->migrationManager ??= MigrationManager::getInstance();
    }


}