<?php

namespace Cube\Tests\Units\Database\Providers;

use Cube\Data\Bunch;
use Cube\Tests\Units\Database\DatabaseProvider;
use PDO;

use function Cube\env;

class MySQLProvider extends DatabaseProvider
{
    public function getConnection(?string $dbName=null): PDO
    {
        $port = env('CUBE_TEST_MYSQL_PORT', 8001);

        $dsn = "mysql:host=127.0.0.1;port=$port";
        if ($dbName)
            $dsn .= ";dbname=$dbName";

        return new PDO($dsn, 'root', env('CUBE_TEST_DATABASE_PASSWORD', 'root'));
    }

    public function createDatabase(string $dbName): PDO
    {
        $this->connection->exec("CREATE DATABASE $dbName");

        return $this->getConnection($dbName);
    }

    public function getDumpPath(): ?string
    {
        return __DIR__ . "/../Dumps/mysql.sql";
    }

    public function databaseExists(string $name): bool
    {
        $statement = $this->connection->query("SHOW DATABASES");
        return Bunch::of($statement->fetchAll())->key("Database")->has($name);
    }
}