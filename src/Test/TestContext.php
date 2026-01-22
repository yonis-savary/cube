<?php

namespace Cube\Test;

use Cube\Console\Commands\Make\Migration;
use Cube\Core\Component;
use Cube\Data\Database\Database;
use Cube\Data\Database\MigrationManager;
use Cube\Env\Configuration;
use Cube\Env\Storage;
use Cube\Web\Router\Router;
use RuntimeException;

class TestContext
{
    use Component;

    protected Storage $storage;
    protected Router $router;
    protected Database $database;
    protected Configuration $configuration;
    protected MigrationManager $migrationManager;

    public function __construct(
        ?Storage $storage=null,
        ?Router $router=null,
        ?Database $database=null,
        ?Configuration $configuration=null,
        ?MigrationManager $migrationManager=null
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

    public function createEmptyApplicationDatabase()
    {
        Database::setInstance(new Database('sqlite', uniqid('app-db-')));

        $migrationManager = MigrationManager::getInstance();
        $migrationManager->executeAllMigrations();
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