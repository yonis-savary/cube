<?php

namespace Cube\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Models\DummyModel;
use Cube\Models\ModelField;
use Cube\Models\ModelGenerator\Table;
use Cube\Models\Relations\HasOne;

class MySQL extends SQLite
{
    public function getSupportedDriver(): array|string
    {
        return 'mysql';
    }

    public function process(): void
    {
        $db = $this->database;

        $this->tables = Bunch::of(
            $db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = {}', [$db->getDatabase()])
        )
            ->key('TABLE_NAME')
            ->map(fn ($x) => $this->buildTable($x))
            ->get()
        ;
    }

    protected function buildTable(string $table): Table
    {
        $db = $this->database;
        $dummyModel = new DummyModel();

        $relations = Bunch::of(
            $db->query(
                'SELECT TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = {}
                AND TABLE_NAME = {}
                AND REFERENCED_COLUMN_NAME IS NOT NULL
            ',
                [$db->getDatabase(), $table]
            )
        )
            ->map(fn ($x) => new HasOne(
                Table::getClassname($table),
                $x['COLUMN_NAME'],
                Table::getClassname($x['REFERENCED_TABLE_NAME']),
                $x['REFERENCED_COLUMN_NAME'],
                $dummyModel
            ))
            ->get()
        ;

        foreach ($relations as $relation) {
            $this->addRelation($relation);
        }

        $primary = null;

        $fields = Bunch::of($db->query("DESCRIBE {$table}"))
            ->map(function ($x) use ($table, &$primary) { return $this->getModelField($table, $x, $primary); })
            ->get()
        ;

        return new Table($table, $fields, $primary, $relations);
    }

    protected function getModelField(string $table, array $desc, ?string &$primary): ModelField
    {
        $fieldName = $desc['Field'];

        $field = new ModelField($fieldName);

        $nullable = ('NO' != $desc['Null']);

        $field->type($this->getModelFieldType($desc['Type']));
        $field->nullable($nullable);
        $field->hasDefault($nullable || (null !== $desc['Default']));

        $tableClassName = Table::getClassname($table);

        /** @var ?HasOne $relation */
        $relation = Bunch::of($this->relations)
            ->onlyInstancesOf(HasOne::class)
            ->first(fn (HasOne $rel) => $rel->isSource($tableClassName, $fieldName))
        ;

        if ($relation) {
            $field->references($relation->toModel, $relation->toColumn);
        }

        if (str_contains($desc['Key'], 'PRI')) {
            $primary = $fieldName;
        }

        if (str_contains($desc['Extra'], 'auto_increment')) {
            $field->autoIncrement();
        }

        return $field;
    }
}
