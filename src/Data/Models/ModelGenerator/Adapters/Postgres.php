<?php

namespace Cube\Data\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Models\ModelField;

class Postgres extends DatabaseAdapter
{
    public static function supports(string $driver): bool
    {
        return $driver === 'pgsql';
    }

    protected function listTables(): array
    {
        return Bunch::fromQuery(
            "SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE';",
            [],
            $this->database
        )
            ->key('table_name')
            ->get()
        ;
    }

    protected function listForeignKeys(string $table): array
    {
        return $this->database->query(
            'SELECT
                key_column_usage.column_name AS "column",
                constraint_column_usage.table_name AS "referencedTable",
                constraint_column_usage.column_name AS "referencedColumn"
            FROM information_schema.table_constraints
            JOIN information_schema.key_column_usage ON (
                table_constraints.constraint_name = key_column_usage.constraint_name AND
                table_constraints.constraint_schema = key_column_usage.constraint_schema
            )
            JOIN information_schema.constraint_column_usage ON (
                constraint_column_usage.constraint_name = table_constraints.constraint_name AND
                constraint_column_usage.constraint_schema = table_constraints.constraint_schema
            )
            WHERE table_constraints.constraint_type = \'FOREIGN KEY\'
            AND table_constraints.table_schema = \'public\'
            AND table_constraints.table_name = {};
        ', [$table]);
    }

    protected function listColumns(string $table): array
    {
        return $this->database->query(
            "SELECT
                column_name,
                udt_name,
                is_nullable,
                column_default,
                character_maximum_length,
                is_generated
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = {}
            ORDER BY ordinal_position;
        ", [$table]);
    }

    protected function getModelField(string $table, array $column, ?string &$primary): ModelField
    {
        $fieldName = $column['column_name'];

        $field = new ModelField($fieldName);

        $nullable = ('NO' != $column['is_nullable']);

        $field->type($this->getModelFieldType($column['udt_name']));
        $field->nullable($nullable);
        $field->hasDefault($nullable || (null !== $column['column_default']));

        if (is_numeric($column['character_maximum_length'])) {
            $field->maximumLength($column['character_maximum_length']);
        }

        if (strtolower($column['is_generated']) !== 'never') {
            $field->generated();
        }

        $this->applyReference($table, $field);

        if (str_contains($column['column_default'] ?? '', 'id_seq')) {
            $primary = $fieldName;
            $field->autoIncrement();
        }

        return $field;
    }
}
