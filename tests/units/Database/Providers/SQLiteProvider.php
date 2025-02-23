<?php

namespace Cube\Tests\Units\Database\Providers;

use Cube\Env\Storage;
use Cube\Tests\Units\Database\DatabaseProvider;
use PDO;

use function Cube\debug;

class SQLiteProvider extends DatabaseProvider
{
    public function getConnection(?string $dbName=null): PDO
    {
        $connection = $dbName ?
            new PDO("sqlite:" . Storage::getInstance()->child('Database')->path($dbName)):
            new PDO('sqlite::memory:');
        $connection->exec("PRAGMA foreign_keys = ON");
        return $connection;
    }

    public function createDatabase(string $dbName): PDO
    {
        return $this->getConnection($dbName);
    }

    public function getDumpPath(): ?string
    {
        return __DIR__ . "/../Dumps/sqlite.sql";
    }

    public function databaseExists(string $name): bool
    {
        return false;
    }
}