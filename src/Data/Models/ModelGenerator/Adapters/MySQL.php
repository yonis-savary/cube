<?php

namespace Cube\Data\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Models\ModelField;

class MySQL extends DatabaseAdapter
{
    public static function supports(string $driver): bool
    {
        return 'mysql' === $driver;
    }

    protected function listTables(): array
    {
        return Bunch::fromQuery(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = {}',
            [$this->database->getDatabase()],
            $this->database
        )
            ->key('TABLE_NAME')
            ->get()
        ;
    }

    protected function listForeignKeys(string $table): array
    {
        return $this->database->query(
            'SELECT COLUMN_NAME AS `column`,
                REFERENCED_TABLE_NAME AS referencedTable,
                REFERENCED_COLUMN_NAME AS referencedColumn
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = {}
            AND TABLE_NAME = {}
            AND REFERENCED_COLUMN_NAME IS NOT NULL
        ',
            [$this->database->getDatabase(), $table]
        );
    }

    protected function listColumns(string $table): array
    {
        return $this->database->query('DESCRIBE `{}`', [$table]);
    }

    protected function getModelField(string $table, array $column, ?string &$primary): ModelField
    {
        $fieldName = $column['Field'];

        $field = new ModelField($fieldName);

        $nullable = ('NO' != $column['Null']);

        $field->type($this->getModelFieldType($column['Type']));
        $field->nullable($nullable);
        $field->hasDefault($nullable || (null !== $column['Default']));

        $extra = strtolower($column['Extra']);

        // DEFAULT_GENERATED marks a default expression (DEFAULT CURRENT_TIMESTAMP), not a generated column
        if (str_contains($extra, 'virtual generated') || str_contains($extra, 'stored generated')) {
            $field->generated();
        } elseif (str_contains($extra, 'default_generated') && null !== $column['Default']) {
            $this->applyDefaultExpression($field, $column['Default']);
        }

        $this->applyReference($table, $field);

        if (str_contains($column['Key'], 'PRI')) {
            $primary = $fieldName;
        }

        if (str_contains($column['Extra'], 'auto_increment')) {
            $field->autoIncrement();
        }

        return $field;
    }
}
