<?php

namespace Cube\Tests\Units\Database\Providers;

use Cube\Data\Bunch;
use Cube\Tests\Units\Database\DatabaseProvider;

use function Cube\env;

class PostgresProvider extends DatabaseProvider
{
    public function getDriver(): string
    {
        return 'pgsql';
    }

    public function getConnection(?string $dbName = null): \PDO
    {
        $port = env('CUBE_TEST_POSTGRES_PORT', 9902);

        $dsn = "pgsql:host=127.0.0.1;port={$port}";
        if ($dbName) {
            $dsn .= ";dbname={$dbName}";
        }

        return new \PDO($dsn, 'postgres', env('CUBE_TEST_DATABASE_PASSWORD', 'root'));
    }

    public function createDatabase(string $dbName): \PDO
    {
        $this->rootConnection()->exec("CREATE DATABASE {$dbName}");

        return $this->getConnection($dbName);
    }

    public function dropDatabase(string $dbName): void
    {
        $connection = $this->rootConnection();

        // Postgres refuses to drop a database that still has a session on it. Teardown
        // closes its own connection first, but the shutdown sweep runs while the leftover
        // Database objects are still alive, so those sessions have to be cut server-side.
        $connection->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$dbName}'");
        $connection->exec("DROP DATABASE IF EXISTS \"{$dbName}\"");
    }

    public function getDumpPath(): ?string
    {
        return __DIR__.'/../Dumps/postgres.sql';
    }

    public function listDatabases(): array
    {
        $statement = $this->rootConnection()->query('SELECT datname FROM pg_database');

        return Bunch::of($statement->fetchAll())->key('datname')->get();
    }
}
