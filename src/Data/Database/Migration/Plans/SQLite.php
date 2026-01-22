<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Bunch;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;
use RuntimeException;

class SQLite extends Plan
{
    public function support(string $driver): bool
    {
        return strtolower($driver) === 'sqlite';
    }

    public function create(string $table, array $fields=[], ?string $additionnalSQL=null) {
        if (!count($fields))
            $fields = [ModelField::id()];

        $fieldsExpressions = new Bunch();
        $relationsAndIndex = new Bunch();

        foreach ($fields as $field) {

            $fieldQuery = $field->name . " ". $this->getModelFieldSQLQuery($field);

            $fieldsExpressions->push($fieldQuery);
            if ($field->hasReference()) {
                $relationsAndIndex->push("FOREIGN KEY (".$field->name.") REFERENCES ". $field->referenceModel ."(". $field->referenceField .")");
            }
        }

        $additionnalSQL = $additionnalSQL ? ", $additionnalSQL": "";
        $this->database->exec("CREATE TABLE `$table` ( ". $fieldsExpressions->merge($relationsAndIndex)->join(",") ." $additionnalSQL )");
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
            $query .= " AUTOINCREMENT";

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

        if ($field->isUnique || $field->hasReference())
            throw new RuntimeException("SQLite does not support constraints on already existing fields");
    }

    public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey) {
        throw new RuntimeException("SQLite does not support constraints on already existing fields");
    }

    public function dropTable(string $table) {
        $this->database->query("DROP TABLE `{}`", [$table]);
    }

    public function dropConstraint(string $table, string $constraintName) {
        $this->database->query("ALTER TABLE `{}` DROP CONSTRAINT `{}`", [$table, $constraintName]);
    }

    public function dropColumn(string $table, string $field) {
        $this->database->query("ALTER TABLE `{}` DROP COLUMN `{}`", [$table, $field]);
    }

    public function alterColumn(string $table, string $field, ModelField $newProperties) {
        $this->database->query("ALTER TABLE `{}` ALTER COLUMN `{}` " . $this->getModelFieldSQLQuery($newProperties), [
            $table, $field
        ]);
    }

    public function addUniqueIndex(string $table, string|array $fields) {
        throw new RuntimeException("SQLite does not support constraints on already existing fields");
    }

    public function renameField(string $table, string $oldFieldName, string $newFieldName) {
        $this->database->query("ALTER TABLE \"$table\" RENAME COLUMN \"$oldFieldName\" TO \"$newFieldName\"");
    }

    public function renameTable(string $oldTableName, string $newTableName) {
        $this->database->query("ALTER TABLE \"$oldTableName\" RENAME TO \"$newTableName\"");
    }
}