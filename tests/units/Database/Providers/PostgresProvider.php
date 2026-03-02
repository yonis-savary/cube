<?php

namespace Cube\Tests\Units\Database\Providers;

use Cube\Data\Bunch;
use Cube\Tests\Units\Database\DatabaseProvider;

use function Cube\env;

class PostgresProvider extends DatabaseProvider
{
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
        $this->connection->exec("CREATE DATABASE {$dbName}");

        return $this->getConnection($dbName);
    }

    public function getDumpPath(): ?string
    {
        return __DIR__.'/../Dumps/postgres.sql';
    }

    public function databaseExists(string $name): bool
    {
        $statement = $this->connection->query('SELECT datname FROM pg_database');

        return Bunch::of($statement->fetchAll())->key('datname')->has($name);
    }
}
