<?php

namespace Cube\Data\Models\ModelGenerator\Adapters;

use Cube\Data\Bunch;
use Cube\Data\Models\DummyModel;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\ModelGenerator\Table;
use Cube\Data\Models\Relations\HasOne;

class Postgres extends SQLite
{
    public function supports(string $driver): bool
    {
        return $driver === 'pgsql';
    }

    public function process(): void
    {
        $this->tables = Bunch::fromQuery(
            "SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE';",
            [],
            $this->database
        )
            ->key('table_name')
            ->map(fn ($x) => $this->buildTable($x))
            ->get()
        ;
    }

    protected function buildTable(string $table): Table
    {
        $db = $this->database;
        $dummyModel = new DummyModel();

        $sqlRelations = $db->query(
            "SELECT
                table_constraints.table_name,
                key_column_usage.column_name,
                table_constraints.constraint_name,
                constraint_column_usage.table_name AS referenced_table_name,
                constraint_column_usage.column_name AS referenced_column_name
            FROM information_schema.table_constraints
            JOIN information_schema.key_column_usage ON (
                table_constraints.constraint_name = key_column_usage.constraint_name AND 
                table_constraints.constraint_schema = key_column_usage.constraint_schema
            )
            JOIN information_schema.constraint_column_usage ON (
                constraint_column_usage.constraint_name = table_constraints.constraint_name AND 
                constraint_column_usage.constraint_schema = table_constraints.constraint_schema
            )
            WHERE table_constraints.constraint_type = 'FOREIGN KEY'
            AND table_constraints.table_schema = 'public'
            AND table_constraints.table_name = {};
        ", [$table]);

        $fields = Bunch::of($sqlRelations)->key('column_name');

        $addedRelationNames = [];

        $relations = Bunch::of($sqlRelations)
            ->map(function ($x) use ($fields, $dummyModel, $table, &$addedRelationNames) {
                $targetModel = Table::getClassname($x['referenced_table_name']);
                $relationName = strtolower($x['column_name']);

                while ($fields->has($relationName) || in_array($relationName, $addedRelationNames))
                    $relationName = "_$relationName";

                $addedRelationNames[] = $relationName;
                return new HasOne(
                    $relationName,
                    Table::getClassname($table),
                    $x['column_name'],
                    $targetModel,
                    $x['referenced_column_name'],
                    $dummyModel
                );
            })
            ->get();

        foreach ($relations as $relation) {
            $this->addRelation($relation);
        }

        $primary = null;

        $fields = Bunch::of($db->query(
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
        ", [$table]))
            ->map(function ($x) use ($table, &$primary) { return $this->getModelField($table, $x, $primary); })
            ->get()
        ;

        return new Table($table, $fields, $primary, $relations);
    }

    protected function getModelField(string $table, array $desc, ?string &$primary): ModelField
    {
        $fieldName = $desc['column_name'];

        $field = new ModelField($fieldName);

        $nullable = ('NO' != $desc['is_nullable']);

        $field->type($this->getModelFieldType($desc['udt_name']));
        $field->nullable($nullable);
        $field->hasDefault($nullable || (null !== $desc['column_default']));

        if (is_numeric($desc['character_maximum_length'])) {
            $field->maximumLength($desc['character_maximum_length']);
        }

        if (strtolower($desc['is_generated']) !== 'never') {
            $field->generated();
        }

        $tableClassName = Table::getClassname($table);

        /** @var ?HasOne $relation */
        $relation = Bunch::of($this->relations)
            ->onlyInstancesOf(HasOne::class)
            ->first(fn (HasOne $rel) => $rel->isSource($tableClassName, $fieldName))
        ;

        if ($relation) {
            $field->references($relation->toModel, $relation->toColumn);
        }

        if (str_contains($desc['column_default'] ?? '', 'id_seq')) {
            $primary = $fieldName;
            $field->autoIncrement();
        }

        return $field;
    }
}
