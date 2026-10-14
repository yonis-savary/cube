<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Database\Migration\SQLPlan;
use Cube\Data\Models\ModelField;

class MySQL extends SQLPlan
{
    public static function supports(string $driver): bool
    {
        return strtolower($driver) === 'mysql';
    }

    protected function getAutoIncrementClause(): string
    {
        return " AUTO_INCREMENT";
    }

    public function alterColumn(string $table, string $field, ModelField $newProperties) {
        $this->assertFieldExists($table, $field);

        if ($field !== $newProperties->name) {
            $this->renameField($table, $field, $newProperties->name);
        }

        $this->database->exec(
            "ALTER TABLE {$this->quote($table)} MODIFY {$this->quote($newProperties->name)} {$this->getModelFieldSQLQuery($newProperties)}"
        );
    }
}
