<?php

namespace Cube\Database\Migration\Adapters;

use Cube\Data\Bunch;
use Cube\Database\MigrationManager;

class MySQL extends MigrationManager
{

    public function migrationWasMade(string $name): bool
    {
        return count($this->database->query(
            "SELECT name FROM `{}` WHERE name = {}
        ", [$this->getMigrationTableName(), $name])) != 0;
    }

    public function createMigrationTableIfInexistant()
    {
        return $this->database->query(
            "CREATE TABLE IF NOT EXISTS `{}` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(200) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ", [$this->getMigrationTableName()]);
    }

    public function markMigrationAsDone(string $name)
    {
        $this->database->query("INSERT INTO `{}` (name) VALUES ({})", [$this->getMigrationTableName(), $name]);
    }

    public function listDoneMigrations(): array
    {
        return Bunch::of(
            $this->database->query("SELECT name FROM `{}` ORDER BY created_at", [$this->getMigrationTableName()])
        )
        ->map(fn($x) => $x["name"])
        ->toArray();
    }

    protected function startTransaction()
    {
        $this->database->exec("START TRANSACTION");
    }

    protected function commitTransaction()
    {
        $this->database->exec("COMMIT");
    }

    protected function rollbackTransaction()
    {
        $this->database->exec("ROLLBACK");
    }
}