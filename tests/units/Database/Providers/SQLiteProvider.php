<?php

namespace Cube\Tests\Units\Database\Providers;

use Cube\Env\Storage;
use Cube\Tests\Units\Database\DatabaseProvider;

class SQLiteProvider extends DatabaseProvider
{
    public function getDriver(): string
    {
        return 'sqlite';
    }

    public function getConnection(?string $dbName = null): \PDO
    {
        $connection = $dbName
            ? new \PDO('sqlite:'.$this->storage()->path($dbName))
            : new \PDO('sqlite::memory:');
        $connection->exec('PRAGMA foreign_keys = ON');

        return $connection;
    }

    public function createDatabase(string $dbName): \PDO
    {
        return $this->getConnection($dbName);
    }

    public function dropDatabase(string $dbName): void
    {
        $this->storage()->unlink($dbName);
    }

    public function getDumpPath(): ?string
    {
        return __DIR__.'/../Dumps/sqlite.sql';
    }

    public function listDatabases(): array
    {
        return array_map(basename(...), $this->storage()->files());
    }

    protected function storage(): Storage
    {
        return Storage::getInstance()->child('Database');
    }
}
