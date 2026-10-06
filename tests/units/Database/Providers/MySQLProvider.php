<?php

namespace Cube\Tests\Units\Database\Providers;

use Cube\Data\Bunch;
use Cube\Tests\Units\Database\DatabaseProvider;

use function Cube\env;

class MySQLProvider extends DatabaseProvider
{
    public function getConnection(?string $dbName = null): \PDO
    {
        $port = env('CUBE_TEST_MYSQL_PORT', 9901);

        $dsn = "mysql:host=127.0.0.1;port={$port}";
        if ($dbName) {
            $dsn .= ";dbname={$dbName}";
        }

        return new \PDO($dsn, 'root', env('CUBE_TEST_DATABASE_PASSWORD', 'root'));
    }

    public function createDatabase(string $dbName): \PDO
    {
        $this->rootConnection()->exec("CREATE DATABASE {$dbName}");

        return $this->getConnection($dbName);
    }

    public function dropDatabase(string $dbName): void
    {
        $this->rootConnection()->exec("DROP DATABASE IF EXISTS {$dbName}");
    }

    public function getDumpPath(): ?string
    {
        return __DIR__.'/../Dumps/mysql.sql';
    }

    public function listDatabases(): array
    {
        $statement = $this->rootConnection()->query('SHOW DATABASES');

        return Bunch::of($statement->fetchAll())->key('Database')->get();
    }
}
