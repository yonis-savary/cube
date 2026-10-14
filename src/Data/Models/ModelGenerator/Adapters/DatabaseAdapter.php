<?php

namespace Cube\Data\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Models\DummyModel;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\ModelGenerator\Table;
use Cube\Data\Models\Relations\HasOne;
use Cube\Data\Models\Relations\Relation;

abstract class DatabaseAdapter
{
    public const SQLITE_TYPES = [
        'INT' => ModelField::INTEGER,
        'INT4' => ModelField::INTEGER,
        'INTEGER' => ModelField::INTEGER,
        'TINYINT(1)' => ModelField::BOOLEAN,
        'TINYINT' => ModelField::INTEGER,
        'SMALLINT' => ModelField::INTEGER,
        'MEDIUMINT' => ModelField::INTEGER,
        'BIGINT' => ModelField::INTEGER,
        'UNSIGNED BIG INT' => ModelField::INTEGER,
        'INT2' => ModelField::INTEGER,
        'INT8' => ModelField::INTEGER,
        'CHARACTER' => ModelField::STRING,
        'VARCHAR' => ModelField::STRING,
        'VARYING CHARACTER' => ModelField::STRING,
        'NCHAR' => ModelField::STRING,
        'NATIVE CHARACTER' => ModelField::STRING,
        'NVARCHAR' => ModelField::STRING,
        'TEXT' => ModelField::STRING,
        'CLOB' => ModelField::STRING,
        'BLOB' => ModelField::STRING,
        'REAL' => ModelField::FLOAT,
        'DOUBLE' => ModelField::FLOAT,
        'DOUBLE PRECISION' => ModelField::FLOAT,
        'FLOAT' => ModelField::FLOAT,
        'NUMERIC' => ModelField::FLOAT,
        'DECIMAL' => ModelField::DECIMAL,
        'BOOLEAN' => ModelField::BOOLEAN,
        'DATE' => ModelField::DATE,
        'DATETIME' => ModelField::DATETIME,
        'TIMESTAMP' => ModelField::TIMESTAMP,
    ];

    protected Database $database;

    /** @var Table[] */
    protected array $tables = [];

    /** @var Relation[] */
    protected array $relations = [];

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    abstract public static function supports(string $driver): bool;

    /** @return string[] */
    abstract protected function listTables(): array;

    /** @return array<array{column:string,referencedTable:string,referencedColumn:string}> */
    abstract protected function listForeignKeys(string $table): array;

    /** @return array<array<string,mixed>> */
    abstract protected function listColumns(string $table): array;

    abstract protected function getModelField(string $table, array $column, ?string &$primary): ModelField;

    public function process(): void
    {
        $this->tables = Bunch::wrap($this->listTables())
            ->map(fn (string $table) => $this->buildTable($table))
            ->get();
    }

    protected function buildTable(string $table): Table
    {
        Table::assertTableName($table);

        $this->addForeignKeyRelations($table, $this->listForeignKeys($table));

        $primary = null;

        $fields = Bunch::wrap($this->listColumns($table))
            ->map(function ($column) use ($table, &$primary) { return $this->getModelField($table, $column, $primary); }) // function() syntax is important, $primary is mutable
            ->values()
            ->get()
        ;

        return new Table($table, $fields, $primary);
    }

    /**
     * @param array<array{column:string,referencedTable:string,referencedColumn:string}> $foreignKeys
     */
    protected function addForeignKeyRelations(string $table, array $foreignKeys): void
    {
        $dummyModel = new DummyModel();
        $columns = Bunch::wrap($foreignKeys)->key('column');
        $addedRelationNames = [];

        foreach ($foreignKeys as $foreignKey) {
            $relationName = strtolower($foreignKey['column']);

            while ($columns->has($relationName) || in_array($relationName, $addedRelationNames))
                $relationName = "_$relationName";

            $addedRelationNames[] = $relationName;

            $this->addRelation(new HasOne(
                $relationName,
                Table::getClassname($table),
                $foreignKey['column'],
                Table::getClassname($foreignKey['referencedTable']),
                $foreignKey['referencedColumn'],
                $dummyModel
            ));
        }
    }

    protected function applyReference(string $table, ModelField $field): void
    {
        $tableClassName = Table::getClassname($table);

        /** @var ?HasOne $relation */
        $relation = Bunch::wrap($this->relations)
            ->onlyInstancesOf(HasOne::class)
            ->first(fn (HasOne $relation) => $relation->isSource($tableClassName, $field->name))
        ;

        if ($relation)
            $field->references($relation->toModel, $relation->toColumn);
    }

    protected function getModelFieldType(string $sqlType): string
    {
        $fieldType = strtoupper($sqlType);
        foreach (self::SQLITE_TYPES as $sqliteType => $modelType) {
            if (str_starts_with($fieldType, $sqliteType))
                return $modelType;
        }

        return ModelField::STRING;
    }

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
