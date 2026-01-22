<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Bunch;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;
use RuntimeException;

class MySQL extends Plan
{
    public function support(string $driver): bool
    {
        return strtolower($driver) === 'mysql';
    }

    public function create(string $table, array $fields=[], ?string $additionnalSQL=null) {
        if (!count($fields))
            $fields = [ModelField::id()];

        $firstColumn = array_shift($fields);
        $fieldName = $firstColumn->name;
        $fieldDescription = $this->getModelFieldSQLQuery($firstColumn);

        $additionnalSQL = $additionnalSQL ? ", $additionnalSQL": "";
        $this->database->exec("CREATE TABLE `$table` ( `$fieldName` $fieldDescription $additionnalSQL )");

        if ($firstColumn->hasReference())
            $this->addForeignKey($table, $firstColumn->name, $firstColumn->referenceModel, $firstColumn->referenceField);

        foreach ($fields as $field) {
            $this->addColumn($table, $field);
        }
    }

    protected function getModelFieldSQLQuery(ModelField $field): string 
    {
        $query = match($field->type) {
            ModelField::STRING    => ($m = $field->maximumLength) ? "VARCHAR($m)": "TEXT",
            ModelField::INTEGER   => "INTEGER",
            ModelField::FLOAT     => "FLOAT",
            ModelField::BOOLEAN   => "BOOLEAN",
            ModelField::DECIMAL   => "DECIMAL(".($field->decimalMaximumDigits ?? 10).",".($field->decimalDigitsToTheRight ?? 5).")",
            ModelField::DATE      => "DATE",
            ModelField::DATETIME  => "DATETIME",
            ModelField::TIMESTAMP => "TIMESTAMP",
        };

        if ($field->isPrimaryKey)
            $query .= " PRIMARY KEY";

        if ($field->autoIncrement)
            $query .= " AUTO_INCREMENT";

        if (!$field->nullable && (!$field->isPrimaryKey))
            $query .= " NOT NULL";

        if ($field->isUnique && (!$field->isPrimaryKey))
            $query .= " UNIQUE";

        if ($field->hasDefault)
            $query .= " DEFAULT " . $this->database->build("{}", [$field->default]);

        return $query;
    }

    public function addColumn(string $table, ModelField $field) {
        $fieldName = $field->name;
        $query = "ALTER TABLE `$table` ADD COLUMN `$fieldName`";

        $query .= " ". $this->getModelFieldSQLQuery($field);

        $this->database->exec($query);

        if ($field->hasReference())
            $this->addForeignKey($table, $field->name, $field->referenceModel, $field->referenceField);
    }

    public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey) {
        $this->database->query("ALTER TABLE `{}` ADD CONSTRAINT FOREIGN KEY (`{}`) REFERENCES `{}`(`{}`)", [
            $table, $field, $foreignTable, $foreignKey
        ]);
    }

    public function dropTable(string $table) {
        if (!$this->database->hasTable($table))
            throw new RuntimeException("Given database does not contains the $table table");

        $this->database->query("DROP TABLE `{}`", [$table]);
    }

    public function dropConstraint(string $table, string $constraintName) {
        if (!$this->database->hasTable($table))
            throw new RuntimeException("Given database does not contains the $table table");

        $this->database->query("ALTER TABLE `{}` DROP CONSTRAINT `{}`", [$table, $constraintName]);
    }

    public function dropColumn(string $table, string $field) {
        if (!$this->database->hasField($table, $field))
            throw new RuntimeException("Given database does not contains the $table($field) column");

        $this->database->query("ALTER TABLE `{}` DROP COLUMN `{}`", [$table, $field]);
    }

    public function alterColumn(string $table, string $field, ModelField $newProperties) {
        if (!$this->database->hasField($table, $field))
            throw new RuntimeException("Given database does not contains the $table($field) column");

        $this->database->query("ALTER TABLE `{}` ALTER COLUMN `{}` " . $this->getModelFieldSQLQuery($newProperties), [
            $table, $field
        ]);
    }

    public function addUniqueIndex(string $table, string|array $fields) {
        $fields = Bunch::of($fields);
        $fieldsExpression = $fields->map(fn($x) => '"'.$x.'"')->join(",");

        $indexName = strtolower("idx_".$table."_" . $fields->join("_"));
        $this->database->query("CREATE UNIQUE INDEX $indexName ON \"{}\"($fieldsExpression)", [$table]);

    }

    public function renameField(string $table, string $oldFieldName, string $newFieldName) {
        $this->database->query("ALTER TABLE `$table` RENAME COLUMN `$oldFieldName` TO `$newFieldName`");
    }

    public function renameTable(string $oldTableName, string $newTableName) {
        $this->database->query("ALTER TABLE `$oldTableName` RENAME TO `$newTableName`");
    }
}