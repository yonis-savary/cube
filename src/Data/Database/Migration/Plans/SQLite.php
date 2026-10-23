<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Bunch;
use Cube\Data\Database\Migration\SQLPlan;
use Cube\Data\Models\ModelField;
use Cube\Env\Logger\Logger;

class SQLite extends SQLPlan
{
    public static function supports(string $driver): bool
    {
        return strtolower($driver) === 'sqlite';
    }

    protected function getAutoIncrementClause(): string
    {
        return " AUTOINCREMENT";
    }

    public function create(string $table, array $fields=[], ?string $additionnalSQL=null) {
        if (!count($fields))
            $fields = [ModelField::id()];

        $fieldsExpressions = new Bunch();
        $relationsAndIndex = new Bunch();

        foreach ($fields as $field) {
            $fieldsExpressions->push("{$this->quote($field->name)} {$this->getModelFieldSQLQuery($field)}");

            if ($field->hasReference())
                $relationsAndIndex->push("FOREIGN KEY ({$this->quote($field->name)}) REFERENCES {$this->quote($field->getReferenceTable())}({$this->quote($field->referenceField)}) {$this->getOnDeleteClause($field->onDeleteBehavior)}");
        }

        $additionnalSQL = $additionnalSQL ? ", $additionnalSQL": "";
        $this->database->exec("CREATE TABLE {$this->quote($table)} ( ".$fieldsExpressions->merge($relationsAndIndex)->join(",")." $additionnalSQL )");
    }

    public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey, ?string $deleteBehavior=null) {
        Logger::getInstance()->warning(
            "SQLite does not support constraints on already existing fields, trying to constraint $table.$field to $foreignTable.$foreignKey"
        );
    }

    public function alterColumn(string $table, string $field, ModelField $newProperties) {
        Logger::getInstance()->warning(
            "Column edition is not supported by SQLite, please rebuild your $table table"
        );
    }
}
