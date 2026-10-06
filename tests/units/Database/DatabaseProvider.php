<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Env\Logger\Logger;

/**
 * Creates a throwaway database for one test, on one DBMS, and remembers it so that
 * nothing survives the process that made it.
 *
 * Subclasses only answer for their DBMS : `getConnection()`, `createDatabase()`,
 * `dropDatabase()` and `databaseExists()`. The bookkeeping below is shared.
 */
abstract class DatabaseProvider
{
    public const RANDOM_NAME_LENGTH = 10;

    /**
     * Databases created during this process, as name => the provider class that made it.
     * Class names rather than instances : holding a provider would also hold its
     * connection to the server for the whole run.
     *
     * @var array<string,class-string<DatabaseProvider>>
     */
    private static array $pendingDatabases = [];

    private static bool $shutdownHookInstalled = false;

    protected ?\PDO $connection = null;

    abstract public function getConnection(?string $dbName = null): \PDO;

    abstract public function createDatabase(string $dbName): \PDO;

    abstract public function dropDatabase(string $dbName): void;

    /** @return string[] */
    abstract public function listDatabases(): array;

    public function databaseExists(string $name): bool
    {
        return in_array($name, $this->listDatabases());
    }

    public static function dropPendingDatabases(): void
    {
        $droppers = [];

        foreach (self::$pendingDatabases as $name => $providerClass) {
            try {
                $dropper = $droppers[$providerClass] ??= new $providerClass();
                $dropper->dropDatabase($name);
            } catch (\Throwable $thrown) {
                Logger::getInstance()->error('Could not drop test database {name} with {provider}', ['name' => $name, 'provider' => $providerClass]);
                Logger::getInstance()->logThrowable($thrown);
            }
        }

        self::$pendingDatabases = [];
    }

    /**
     * Drops every database a provider could have made, whatever process made it : this is what
     * catches the runs that were killed before their shutdown sweep. Only call it while no test runs.
     */
    public static function dropLeftoverDatabases(): void
    {
        foreach (Autoloader::classesThatExtends(self::class) as $providerClass) {
            $provider = new $providerClass();

            foreach ($provider->listDatabases() as $name) {
                if (self::isRandomDatabaseName($name))
                    $provider->dropDatabase($name);
            }
        }
    }

    public static function isRandomDatabaseName(string $name): bool
    {
        return 1 === preg_match('/^[a-z]{'.self::RANDOM_NAME_LENGTH.'}$/', $name);
    }

    public function getDumpPath(): ?string
    {
        return null;
    }

    public function getEmptyDatabase(): Database
    {
        try {
            $name = $this->getRandomDatabaseName();
            $connection = $this->createDatabase($name);

            $this->rememberCreatedDatabase($name);

            if ($file = $this->getDumpPath()) {
                $connection->exec(file_get_contents($file));
            }

            return Database::fromPDO($connection, $name);
        } catch (\Throwable $err) {
            $logger = Logger::getInstance();
            $logger->error('Error in '.static::class);
            $logger->logThrowable($err);

            throw $err;
        }
    }

    /**
     * Connection to the server itself, opened on first use so that building a
     * provider stays free.
     */
    protected function rootConnection(): \PDO
    {
        return $this->connection ??= $this->getConnection(null);
    }

    protected function rememberCreatedDatabase(string $name): void
    {
        self::$pendingDatabases[$name] = static::class;

        if (self::$shutdownHookInstalled) {
            return;
        }

        self::$shutdownHookInstalled = true;
        register_shutdown_function(self::dropPendingDatabases(...));
    }

    protected function getRandomDatabaseName(): string
    {
        do {
            $name = strtolower(substr(preg_replace('/[^a-z]/i', '', base64_encode(random_bytes(50))), 0, self::RANDOM_NAME_LENGTH));
        } while ($this->databaseExists($name));

        return $name;
    }
}
