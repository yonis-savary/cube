<?php

namespace Cube\Models\ModelGenerator\Adapters;

use Cube\Database\Database;
use Cube\Models\ModelGenerator\Table;
use Cube\Models\Relations\Relation;

abstract class DatabaseAdapter
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

    abstract public function getSupportedDriver(): array|string;

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
