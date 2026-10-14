<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Database\Migration\SQLPlan;
use Cube\Data\Models\ModelField;

class PostgreSQL extends SQLPlan
{
    public static function supports(string $driver): bool
    {
        return strtolower($driver) === 'pgsql';
    }

    protected function getAutoIncrementClause(): string
    {
        return "";
    }

    protected function getColumnType(ModelField $field): string
    {
        if ($field->isPrimaryKey && $field->autoIncrement)
            return "SERIAL";

        return ModelField::DATETIME === $field->type
            ? "TIMESTAMP"
            : parent::getColumnType($field);
    }

    public function alterColumn(string $table, string $field, ModelField $newProperties) {
        $this->assertFieldExists($table, $field);

        if ($field !== $newProperties->name) {
            $this->renameField($table, $field, $newProperties->name);
        }

        $column = "ALTER TABLE {$this->quote($table)} ALTER COLUMN {$this->quote($newProperties->name)}";
        $typeQuery = ($newProperties->isPrimaryKey && $newProperties->autoIncrement)
            ? "SERIAL"
            : "TYPE " . $this->getColumnType($newProperties);

        $this->database->exec("$column $typeQuery");

        $this->database->exec(
            (!$newProperties->nullable && (!$newProperties->isPrimaryKey))
                ? "$column SET NOT NULL"
                : "$column DROP NOT NULL"
        );

        if ($newProperties->isUnique && (!$newProperties->isPrimaryKey))
            $this->database->exec("ALTER TABLE {$this->quote($table)} ADD UNIQUE ({$this->quote($newProperties->name)})");

        if ($newProperties->hasDefault)
            $this->database->exec("$column SET DEFAULT " . $this->database->build("{}", [$newProperties->default]));
    }
}
