<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Logger\Logger;
use Cube\Tests\Units\Database\Providers\MySQLProvider;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use PDO;
use Throwable;

use function Cube\debug;

abstract class DatabaseProvider
{
    protected PDO $connection;

    abstract public function getConnection(?string $dbName=null): PDO;
    abstract public function createDatabase(string $dbName): PDO;
    abstract public function databaseExists(string $name): bool;

    public function __construct()
    {
        $this->connection = $this->getConnection(null);
    }

    public function getDumpPath(): ?string
    {
        return null;
    }

    protected function getRandomDatabaseName(): string
    {
        do
        {
            $name = strtolower(substr(preg_replace("/[^a-z]/i", "", base64_encode(random_bytes(50))), 0, 10));
        }
        while ($this->databaseExists($name));

        return $name;
    }

    public function getEmptyDatabase(): Database
    {
        try
        {
            $name = $this->getRandomDatabaseName();
            $connection = $this->createDatabase($name);

            if ($file = $this->getDumpPath())
                $connection->exec(file_get_contents($file));

            return Database::fromPDO($connection);
        }
        catch (Throwable $err)
        {
            $logger = Logger::getInstance();
            $logger->error("Error in " . get_called_class());
            $logger->logThrowable($err);
            throw $err;
        }
    }
}