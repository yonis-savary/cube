<?php

namespace Cube\Data\Database\Migration;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelField;
use InvalidArgumentException;

abstract class Plan
{
    /**
     * @var array<string,ModelField[]>
     */
    protected array $tableDiff = [];
    protected bool $allowExperimentalFeatures = false;

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

    public function allowExperimentalFeatures() {
        $this->allowExperimentalFeatures = true;
    }

    public function tableDiffAddTable(string $table, array $fields) {
        $this->tableDiff[$table] = $fields;
    }

    public function tableDiffDropTable(string $table) {
        if (isset($this->tableDiff[$table]))
            unset($this->tableDiff[$table]);
    }

    public function editTableField(string $table, string $field, ModelField $attributes) {
        $this->tableDiff[$table] ??= [];

        $table = &$this->tableDiff[$table];

        for ($i=0; $i<count($table); $i++) {
            $tableField = $table[$i];
            if ($tableField->name === $field) {
                $table[$i] = $attributes;
                return;
            }
        }

        $table[] = $attributes;
    }

    public function tableDiffHasTable(string $table): bool 
    {
        return array_key_exists($table, $this->tableDiff);
    }

    public function tableDiffHasField(string $table, string $field): bool 
    {
        if (!$this->tableDiffHasTable($table))
            return false;

        foreach ($this->tableDiff[$table] as $field) {
            if ($field->name === $field)
                return true;
        }
        return false;
    }

    public function addTableDiffField(string $table, ModelField $attributes) {
        $this->tableDiff[$table] ??= [];
        $this->tableDiff[$table][] = $attributes;
    }

    public function renameTableDiffTable(string $table, string $newName) 
    {
        if ($this->tableDiffHasTable($table)) {
            $this->tableDiff[$newName] = $this->tableDiff[$table];
            unset($this->tableDiff[$table]);
        }
    }

    public function renameTableDiffField(string $table, string $oldFieldName, string $newFieldName)
    {
        if ($this->tableDiffHasTable($table)) {
            foreach ($this->tableDiff[$table] as &$field) {
                if ($field->name === $oldFieldName) {
                    $field->name = $newFieldName;
                    return;
                }
            }
        }
    }

    public function getTableFields(string $table) {
        $model = Bunch::fromExtends(Model::class)->first(fn($model) => $model->table() === $table);

        $modelFields = $model ? $model->fields() : [];
        $diffFields = Bunch::of($this->tableDiff[$table] ?? [])
            ->zip(fn($field) => [$field->name, $field]);

        return array_values(array_merge($modelFields, $diffFields));
    }
}