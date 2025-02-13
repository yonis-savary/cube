<?php

namespace YonisSavary\Cube\Models\ModelGenerator\Adapters;

use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Models\Relations\Relation;
use YonisSavary\Cube\Models\ModelGenerator\Table;

abstract class AbstractDatabaseAdapter
{
    protected Database $database;

    /** @var Table[] */
    protected array $tables = [];

    /** @var Relation[] */
    protected array $relations = [];

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    abstract public function getSupportedDriver(): string|array ;

    abstract public function process(): void;

    public function addTable(Table $table): void
    {
        $this->tables[] = $table;
    }

    public function addRelation(Relation $relation): void
    {
        $this->relations[] = $relation;
    }

    /** @return Table[] */
    public function getTables(): array
    {
        return $this->tables;
    }

    /** @return Relation[] */
    public function getRelations(): array
    {
        return $this->relations;
    }
}