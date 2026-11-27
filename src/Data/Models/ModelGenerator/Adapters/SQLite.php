<?php

namespace Cube\Data\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Database\DatabaseDriver;
use Cube\Data\Models\ModelField;

class SQLite extends DatabaseAdapter
{
    /** @var string[] */
    protected array $tablesWithAutoIncrement = [];

    public static function supports(string $driver): bool
    {
        return DatabaseDriver::SQLite->value === $driver;
    }

    public function process(): void
    {
        // sqlite_sequence only exists once a table declares AUTOINCREMENT, and only lists a table after its first insert
        $this->tablesWithAutoIncrement = Bunch::fromQuery("SELECT name, sql FROM sqlite_master WHERE type='table'", [], $this->database)
            ->filter(fn (array $table) => preg_match('/\bAUTOINCREMENT\b/i', (string) $table['sql']))
            ->key('name')
            ->get()
        ;

        parent::process();
    }

    protected function listTables(): array
    {
        return Bunch::fromQuery("SELECT name FROM sqlite_master WHERE type='table' AND name <> 'sqlite_sequence'", [], $this->database)
            ->key('name')
            ->get()
        ;
    }

    protected function listForeignKeys(string $table): array
    {
        return Bunch::fromQuery("PRAGMA foreign_key_list('{$table}')", [], $this->database)
            ->map(fn ($foreignKey) => [
                'column' => $foreignKey['from'],
                'referencedTable' => $foreignKey['table'],
                'referencedColumn' => $foreignKey['to'],
            ])
            ->get()
        ;
    }

    protected function listColumns(string $table): array
    {
        return Bunch::fromQuery('PRAGMA table_xinfo({})', [$table], $this->database)
            ->filter(fn ($column) => in_array($column['hidden'], ['0', '2'])) // 0 => visible column, 2 => generated column
            ->get()
        ;
    }

    protected function getModelField(string $table, array $column, ?string &$primary): ModelField
    {
        $fieldName = $column['name'];

        $field = new ModelField($fieldName);

        $nullable = 0 == $column['notnull'];

        $field->type($this->getModelFieldType($column['type']));
        $field->nullable($nullable);
        $field->hasDefault($nullable || (null !== $column['dflt_value']));

        // A literal comes back quoted ('text') or as a number, an expression as a keyword or in parentheses
        $default = $column['dflt_value'];
        if (null !== $default && (str_starts_with($default, '(') || preg_match('/^CURRENT_(TIMESTAMP|DATE|TIME)$/i', $default))) {
            $this->applyDefaultExpression($field, $default);
        }

        $this->applyReference($table, $field);

        if ($column['pk']) {
            $primary = $fieldName;
        }

        if ($column['pk'] && in_array($table, $this->tablesWithAutoIncrement)) {
            $field->autoIncrement();
        }
        if ('2' == $column['hidden']) {
            $field->generated();
        }

        return $field;
    }
}
