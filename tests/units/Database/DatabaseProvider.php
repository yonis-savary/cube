<?php

namespace Cube\Tests\Units\Database;

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

    abstract public function databaseExists(string $name): bool;

    /**
     * Drops every database this process created, one provider per driver.
     *
     * Signals are out of reach — nothing runs on SIGINT or SIGKILL. Restarting the
     * services is what covers those, the data directories being tmpfs.
     */
    public static function dropPendingDatabases(): void
    {
        $droppers = [];

        foreach (self::$pendingDatabases as $name => $providerClass) {
            $dropper = $droppers[$providerClass] ??= new $providerClass();
            $dropper->dropDatabase($name);
        }

        self::$pendingDatabases = [];
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
            $name = strtolower(substr(preg_replace('/[^a-z]/i', '', base64_encode(random_bytes(50))), 0, 10));
        } while ($this->databaseExists($name));

        return $name;
    }
}
