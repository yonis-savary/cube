<?php

namespace Cube\Test;

use Cube\Core\Component;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Adapters\SQLite;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Data\Database\MigrationManager;
use Cube\Env\Configuration;
use Cube\Env\Storage;
use Cube\Web\Router\Router;

use function Cube\debug;

class TestContext
{
    use Component;

    protected Storage $storage;
    protected Router $router;
    protected Database $database;
    protected Database $emptyApplicationDatabase;
    protected Configuration $configuration;
    protected MigrationManager $migrationManager;

    public function __construct(
        ?Storage $storage=null,
        ?Router $router=null,
        ?Database $database=null,
        ?Configuration $configuration=null
    )
    {
        $this->storage = $storage ?? Storage::getInstance();
        $this->router = $router ?? Router::getInstance();
        $this->database = $database ?? Database::getInstance();

        $this->configuration = $configuration ?? Configuration::getInstance();

        $this->replaceGlobalInstances();
    }

    public function replaceGlobalInstances()
    {
        Storage::getInstance($this->storage);
        Router::setInstance($this->router);
        Database::setInstance($this->database);
        Configuration::setInstance($this->configuration);

        MigrationManager::removeInstance();
        $this->migrationManager = MigrationManager::getInstance();
    }

    public function createEmptyApplicationDatabase(): Database
    {
        $database = new Database('sqlite', uniqid('app-db-'));

        $database->asGlobalInstance(function() use ($database) {
            $config = MigrationManagerConfiguration::resolve(Configuration::getInstance());
            $migrationManager= new SQLite($database, $config);
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
        return $this->database;
    }

    public function getConfiguration(): Configuration {
        return $this->configuration;
    }

    public function getMigrationManager(): MigrationManager {
        return $this->migrationManager;
    }


}