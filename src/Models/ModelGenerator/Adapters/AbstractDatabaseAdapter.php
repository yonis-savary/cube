<?php

namespace YonisSavary\Cube\Models\ModelGenerator\Adapters;

use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Models\ModelGenerator\Relation;
use YonisSavary\Cube\Models\ModelGenerator\Table;

abstract class AbstractDatabaseAdapter
{
    protected Database $database;

    /** @var array<Table> */
    protected array $tables = [];

    /** @var array<Relation> */
    protected array $constraints = [];

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    abstract public function getSupportedDriver(): string|array ;

    abstract public function process(): void;

    public function getTables(): array
    {
        return $this->tables;
    }

    /** @return array<Relation> */
    public function getConstraints(): array
    {
        return $this->constraints;
    }
}