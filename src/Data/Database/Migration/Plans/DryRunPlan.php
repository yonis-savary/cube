<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Database\Migration\Plans\Exceptions\DryRunPlanException;
use Cube\Data\Models\ModelField;

class DryRunPlan extends Plan
{
    public function __construct(
        protected Database $database
    ){}

    public function support(string $driver): bool {
        return false;
    }

    /**
     * Create a new table
     * @param ModelField[] $fields
     */
    public function create(string $table, array $fields=[], ?string $additionnalSQL=null) {
        $this->tableDiffAddTable($table, $fields);
    }

    /**
     * Edit existing table
     */
    public function dropTable(string $table) {
        if ($this->database->hasTable($table) || $this->tableDiffHasTable($table)) {
            $this->tableDiffDropTable($table);
            return true;
        }

        throw new DryRunPlanException("Cannot drop table $table");
    }

    public function dropConstraint(string $table, string $constraintName) {

    }

    protected function columnExists(string $table, string $column) {
        return $this->database->hasField($table, $column) || $this->tableDiffHasField($table, $column);
    }

    protected function tableExists(string $table) {
        return $this->database->hasTable($table) || $this->tableDiffHasTable($table);
    }

    public function dropColumn(string $table, string $column) {
        if (!$this->columnExists($table, $column))
            throw new DryRunPlanException("Cannot find $table.$column column");
    }

    public function alterColumn(string $table, string $column, ModelField $newProperties)
    {
        if (!$this->columnExists($table, $column))
            throw new DryRunPlanException("Cannot find $table.$column column");

        $this->editTableField($table, $column, $newProperties);
    }

    public function addColumn(string $table, ModelField $modelField) {
        $column = $modelField->name;
        if ($this->columnExists($table, $column))
            throw new DryRunPlanException("$table.$column already exists");

        $this->addTableDiffField($table, $modelField);
    }

    public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey) {
        if (!$this->columnExists($table, $field))
            throw new DryRunPlanException("$table.$field does not exists");

        if (!$this->columnExists($foreignTable, $foreignKey))
            throw new DryRunPlanException("$foreignTable.$foreignKey does not exists");
    }

    /**
     * @param string[]|string $fields
     */
    public function addUniqueIndex(string $table, string|array $fields) {
        $fields = Bunch::of($fields)->toArray();

        foreach ($fields as $field) {
            if (!$this->columnExists($table, $field))
                throw new DryRunPlanException("$table.$field does not exists");
        }
    }

    public function renameField(string $table, string $oldFieldName, string $newFieldName) {
        if (!$this->columnExists($table, $oldFieldName)) {
            throw new DryRunPlanException("$table.$oldFieldName does not exists");
        }

        $this->renameTableDiffField($table, $oldFieldName, $newFieldName);
    }

    public function renameTable(string $oldTableName, string $newTableName) 
    {
        if (!$this->tableExists($oldTableName))
            throw new DryRunPlanException("Table $oldTableName not found");

        if ($this->tableExists($newTableName))
            throw new DryRunPlanException("Table $newTableName already exists");

        $this->renameTableDiffTable($oldTableName, $newTableName);
    }
}