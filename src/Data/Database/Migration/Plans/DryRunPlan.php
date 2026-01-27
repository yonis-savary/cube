<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Database\Migration\Plans\Exceptions\DryRunPlanException;
use Cube\Data\Models\ModelField;

use function Cube\debug;

class DryRunPlan extends Plan
{
    /**
     * @var array<string,ModelField[]>
     */
    protected $planTables = [];
    protected $renamedTables = [];
    protected $renamedFields = [];

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
        $this->planTables[$table] = $fields;
    }

    /**
     * Edit existing table
     */
    public function dropTable(string $table) {
        if ($this->database->hasTable($table) || array_key_exists($table, $this->planTables)) {
            return true;
        }

        throw new DryRunPlanException("Cannot drop table $table");
    }

    public function dropConstraint(string $table, string $constraintName) {

    }

    protected function columnExists(string $table, string $column) {
        if ($this->database->hasField($table, $column)) {
            return true;
        }

        if ($fields = $this->planTables[$table] ?? false) {
            $matchingColumn = Bunch::of($fields)->first(fn($x) => $x->name === $column);
            if ($matchingColumn)
                return true;
        }

        return false;
    }

    public function dropColumn(string $table, string $column) {
        if (!$this->columnExists($table, $column))
            throw new DryRunPlanException("Cannot find $table.$column column");
    }

    public function alterColumn(string $table, string $column, ModelField $newProperties)
    {
        if (!$this->columnExists($table, $column))
            throw new DryRunPlanException("Cannot find $table.$column column");
    }

    public function addColumn(string $table, ModelField $modelField) {
        $column = $modelField->name;
        if ($this->columnExists($table, $column))
            throw new DryRunPlanException("$table.$column already exists");
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

        if ($fields = $this->planTables[$table] ?? false) {
            foreach ($fields as $field) {
                if ($field->name === $oldFieldName)
                    $field->name = $newFieldName;
            }
        } else {
            $this->planTables[$table] = [new ModelField($newFieldName)];
        }
    }

    public function renameTable(string $oldTableName, string $newTableName) 
    {
        $oldTableExists = $this->database->hasTable($oldTableName) || array_key_exists($oldTableName, $this->planTables);
        if (!$oldTableExists)
            throw new DryRunPlanException("Table $oldTableName not found");

        $newTableAlreadyExists = array_key_exists($newTableName, $this->planTables) || $this->database->hasTable($newTableName);
        if ($newTableAlreadyExists)
            throw new DryRunPlanException("Table $newTableName already exists");

        if (array_key_exists($oldTableName, $this->planTables)) {
            $this->planTables[$newTableName] = $this->planTables[$oldTableName];
            unset($this->planTables[$oldTableName]);
        } else {
            $this->planTables[$newTableName] = [];
        }
    }
}