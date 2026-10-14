<?php

namespace Cube\Data\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Models\ModelField;

class SQLite extends DatabaseAdapter
{
    protected array $tablesWithSequence = [];

    public static function supports(string $driver): bool
    {
        return $driver === 'sqlite';
    }

    public function process(): void
    {
        $this->tablesWithSequence = Bunch::fromQuery('SELECT name FROM sqlite_sequence', [], $this->database)
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
            ->filter(fn($column) => in_array($column['hidden'], ['0', '2'])) // 0 => visible column, 2 => generated column
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

        $this->applyReference($table, $field);

        if ($column['pk']) {
            $primary = $fieldName;
        }

        if ($column['pk'] && in_array($table, $this->tablesWithSequence)) {
            $field->autoIncrement();
        }
        if ($column['hidden'] == '2') {
            $field->generated();
        }

        return $field;
    }
}
