<?php

namespace Cube\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Models\DummyModel;
use Cube\Models\ModelField;
use Cube\Models\Relations\HasOne;
use Cube\Models\ModelGenerator\Table;

class SQLite extends DatabaseAdapter
{
    const SQLITE_TYPES = [
        'INT' => ModelField::INTEGER,
        'INTEGER' => ModelField::INTEGER,
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
        'TIMESTAMP' => ModelField::TIMESTAMP

    ];

    protected array $tablesWithSequence = [];

    public function getSupportedDriver(): string|array
    {
        return "sqlite";
    }

    protected function getModelFieldType(string $fieldSqliteType): string
    {
        $fieldType = strtoupper($fieldSqliteType);
        foreach (self::SQLITE_TYPES as $sqliteType => $modelType)
        {
            if (str_starts_with($fieldType, $sqliteType))
                return $modelType;
        }
        return ModelField::STRING;
    }

    protected function getModelField(string $table, array $desc, ?string &$primary): ModelField
    {
        $fieldName = $desc["name"];

        $field = new ModelField($fieldName);

        $nullable = $desc["notnull"] == 0;

        $field->type($this->getModelFieldType($desc["type"]));
        $field->nullable($nullable);
        $field->hasDefault($nullable || ($desc["dflt_value"] !== null));

        $tableClassName = Table::getClassname($table);

        /** @var ?HasOne $relation */
        $relation = Bunch::of($this->relations)
        ->onlyInstancesOf(HasOne::class)
        ->first(fn(HasOne $rel) => $rel->isSource($tableClassName, $fieldName));

        if ($relation)
            $field->references($relation->toModel, $relation->toColumn);

        if ($desc["pk"])
            $primary = $desc["name"];

        if ($desc["pk"] && in_array($table, $this->tablesWithSequence))
            $field->autoIncrement();

        return $field;
    }

    protected function buildTable(string $table): Table
    {
        $db = $this->database;

        $dummyModel = new DummyModel();

        $relations = Bunch::of($db->query("PRAGMA foreign_key_list('$table')"))
        ->map(fn($x) => new HasOne(Table::getClassname($table), $x["from"], Table::getClassname($x["table"]), $x["to"], $dummyModel))
        ->toArray();

        foreach ($relations as $relation)
            $this->addRelation($relation);

        $primary = null;

        $fields = Bunch::of($db->query("PRAGMA table_info({})", [$table]))
        ->map(function($x) use ($table, &$primary) { return $this->getModelField($table, $x, $primary); })
        ->get();

        return new Table($table, $fields, $primary, $relations);
    }

    public function process(): void
    {
        $db = $this->database;

        $this->tablesWithSequence = Bunch::of($db->query("SELECT * FROM sqlite_sequence"))
        ->map(fn($x) => $x["name"])
        ->get();

        $this->tables = Bunch::of($db->query("SELECT * FROM sqlite_master WHERE type='table' AND name <> 'sqlite_sequence'"))
        ->map(fn($x) => $this->buildTable($x["name"]))
        ->get();
    }
}