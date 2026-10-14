<?php

namespace Cube\Data\Database\Migration\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Database\MigrationManager;

class Postgres extends MigrationManager
{
    public static function supports(string $driver): bool
    {
        return strtolower($driver) === 'pgsql';
    }

    public function migrationWasMade(string $name): bool
    {
        return 0 != count($this->database->query(
            'SELECT name FROM "{}" WHERE name = {}
        ',
            [$this->getMigrationTableName(), $name]
        ));
    }

    public function createMigrationTableIfInexistant()
    {
        return $this->database->query(
            'CREATE TABLE IF NOT EXISTS "{}" (
                id SERIAL PRIMARY KEY,
                name VARCHAR(200) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
            [$this->getMigrationTableName()]
        );
    }

    public function markMigrationAsDone(string $name)
    {
        $this->database->query('INSERT INTO "{}" (name) VALUES ({})', [$this->getMigrationTableName(), $name]);
    }

    public function markMigrationAsUndone(string $name)
    {
        $this->database->query(
            'DELETE FROM "{}" WHERE name = {}',
            [$this->getMigrationTableName(), $name]
        );
    }

    public function listDoneMigrations(): array
    {
        return Bunch::wrap(
            $this->database->query('SELECT name FROM "{}" ORDER BY id', [$this->getMigrationTableName()])
        )
            ->map(fn ($x) => $x['name'])
            ->toArray()
        ;
    }
}
