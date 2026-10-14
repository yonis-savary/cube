<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Bunch;
use Cube\Data\Models\ModelField;
use RuntimeException;

abstract class SQLPlan extends Plan
{
    abstract protected function getAutoIncrementClause(): string;

    protected function quote(string $identifier): string
    {
        return $this->database->getQueryBuilder()->quote($identifier);
    }

    protected function getColumnType(ModelField $field): string
    {
        return match($field->type) {
            ModelField::STRING    => ($m = $field->maximumLength) ? "VARCHAR($m)": "TEXT",
            ModelField::INTEGER   => "INTEGER",
            ModelField::FLOAT     => "FLOAT",
            ModelField::BOOLEAN   => "BOOLEAN",
            ModelField::DECIMAL   => "DECIMAL(".($field->decimalMaximumDigits ?? 10).",".($field->decimalDigitsToTheRight ?? 5).")",
            ModelField::DATE      => "DATE",
            ModelField::DATETIME  => "DATETIME",
            ModelField::TIMESTAMP => "TIMESTAMP",
        };
    }

    protected function getModelFieldSQLQuery(ModelField $field): string
    {
        $query = $this->getColumnType($field);

        if ($field->isPrimaryKey)
            $query .= " PRIMARY KEY";

        if ($field->autoIncrement)
            $query .= $this->getAutoIncrementClause();

        if (!$field->nullable && (!$field->isPrimaryKey))
            $query .= " NOT NULL";

        if ($field->isUnique && (!$field->isPrimaryKey))
            $query .= " UNIQUE";

        if ($field->hasDefault)
            $query .= " DEFAULT " . $this->database->build("{}", [$field->default]);

        return $query;
    }

    protected function getOnDeleteClause(?string $deleteBehavior): string
    {
        return match ($deleteBehavior) {
            ModelField::ON_DELETE_CASCADE => "ON DELETE CASCADE",
            ModelField::ON_DELETE_SET_NULL => "ON DELETE SET NULL",
            default => "",
        };
    }

    protected function assertTableExists(string $table): void
    {
        if (!$this->database->hasTable($table))
            throw new RuntimeException("Could not find the [{$table}] table in the database");
    }

    protected function assertFieldExists(string $table, string $field): void
    {
        if (!$this->database->hasField($table, $field))
            throw new RuntimeException("Could not find the [{$table}.{$field}] column in the database");
    }

    public function create(string $table, array $fields=[], ?string $additionnalSQL=null) {
        if (!count($fields))
            $fields = [ModelField::id()];

        $firstColumn = array_shift($fields);

        $additionnalSQL = $additionnalSQL ? ", $additionnalSQL": "";
        $this->database->exec(
            "CREATE TABLE {$this->quote($table)} ( {$this->quote($firstColumn->name)} {$this->getModelFieldSQLQuery($firstColumn)} $additionnalSQL )"
        );

        if ($firstColumn->hasReference())
            $this->addForeignKey($table, $firstColumn->name, $firstColumn->getReferenceTable(), $firstColumn->referenceField, $firstColumn->onDeleteBehavior);

        foreach ($fields as $field)
            $this->addColumn($table, $field);
    }

    public function addColumn(string $table, ModelField $field) {
        $this->database->exec("ALTER TABLE {$this->quote($table)} ADD COLUMN {$this->quote($field->name)} {$this->getModelFieldSQLQuery($field)}");

        if ($field->hasReference())
            $this->addForeignKey($table, $field->name, $field->getReferenceTable(), $field->referenceField, $field->onDeleteBehavior);
    }

    public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey, ?string $deleteBehavior=null) {
        $this->database->exec(
            "ALTER TABLE {$this->quote($table)} ADD FOREIGN KEY ({$this->quote($field)}) REFERENCES {$this->quote($foreignTable)}({$this->quote($foreignKey)}) {$this->getOnDeleteClause($deleteBehavior)}"
        );
    }

    public function dropTable(string $table) {
        $this->assertTableExists($table);

        $this->database->exec("DROP TABLE {$this->quote($table)}");
    }

    public function dropConstraint(string $table, string $constraintName) {
        $this->assertTableExists($table);

        $this->database->exec("ALTER TABLE {$this->quote($table)} DROP CONSTRAINT {$this->quote($constraintName)}");
    }

    public function dropColumn(string $table, string $field) {
        $this->assertFieldExists($table, $field);

        $this->database->exec("ALTER TABLE {$this->quote($table)} DROP COLUMN {$this->quote($field)}");
    }

    public function addUniqueIndex(string $table, string|array $fields) {
        $fields = Bunch::wrap($fields);
        $fieldsExpression = $fields->map(fn ($field) => $this->quote($field))->join(",");

        $indexName = strtolower("idx_".$table."_" . $fields->join("_"));
        $this->database->exec("CREATE UNIQUE INDEX {$this->quote($indexName)} ON {$this->quote($table)}($fieldsExpression)");
    }

    public function renameField(string $table, string $oldFieldName, string $newFieldName) {
        $this->database->exec("ALTER TABLE {$this->quote($table)} RENAME COLUMN {$this->quote($oldFieldName)} TO {$this->quote($newFieldName)}");
    }

    public function renameTable(string $oldTableName, string $newTableName) {
        $this->database->exec("ALTER TABLE {$this->quote($oldTableName)} RENAME TO {$this->quote($newTableName)}");
    }
}
