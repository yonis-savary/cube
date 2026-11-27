<?php

namespace Cube\Tests\Units\Database\Classes;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\DatabaseProvider;
use Cube\Tests\Units\Database\Providers\MySQLProvider;
use Cube\Tests\Units\Database\Providers\PostgresProvider;

use function Cube\env;

/**
 * Builds a database Cube can reconnect to, unlike the PDO-backed ones of the providers, and closes
 * its connection from the server side, the way a `wait_timeout` or a failover does.
 */
trait LosesItsConnection
{
    protected function reconnectableDatabase(DatabaseProvider $provider): Database
    {
        $name = $provider->getEmptyDatabase()->getDatabase();

        return match (true) {
            $provider instanceof MySQLProvider => new Database('mysql', $name, '127.0.0.1', (int) env('CUBE_TEST_MYSQL_PORT', 9901), 'root', env('CUBE_TEST_DATABASE_PASSWORD', 'root')),
            $provider instanceof PostgresProvider => new Database('pgsql', $name, '127.0.0.1', (int) env('CUBE_TEST_POSTGRES_PORT', 9902), 'postgres', env('CUBE_TEST_DATABASE_PASSWORD', 'root')),
        };
    }

    protected function closeFromTheServer(Database $database, DatabaseProvider $provider): void
    {
        $other = $provider->getConnection();

        match ($database->getDriver()) {
            'mysql' => $other->exec('KILL '.(int) $database->query('SELECT CONNECTION_ID() AS id')[0]['id']),
            'pgsql' => $other->query('SELECT pg_terminate_backend('.(int) $database->query('SELECT pg_backend_pid() AS pid')[0]['pid'].')'),
        };
    }

    public static function getServerProviders(): array
    {
        return [
            'mysql' => [new MySQLProvider()],
            'postgres' => [new PostgresProvider()],
        ];
    }
}
