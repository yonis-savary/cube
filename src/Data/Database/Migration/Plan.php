<?php

namespace Cube\Data\Database\Migration;

use Cube\Data\Database\Database;
use Cube\Data\Models\ModelField;

abstract class Plan
{
    public function __construct(
        protected Database $database
    ){}

    abstract public function support(string $driver): bool;

    /**
     * Create a new table
     * @param ModelField[] $fields
     */
    abstract public function create(string $table, array $fields=[], ?string $additionnalSQL=null);

    /**
     * Edit existing table
     */
    abstract public function dropTable(string $table);

    abstract public function dropConstraint(string $table, string $constraintName);

    abstract public function dropColumn(string $table, string $column);

    abstract public function alterColumn(string $table, string $column, ModelField $newProperties);

    abstract public function addColumn(string $table, ModelField $modelField);

    abstract public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey);

    /**
     * @param string[]|string $fields
     */
    abstract public function addUniqueIndex(string $table, string|array $fields);

    abstract public function renameField(string $table, string $oldFieldName, string $newFieldName);

    abstract public function renameTable(string $oldTableName, string $newTableName);
}