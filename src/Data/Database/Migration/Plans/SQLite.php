<?php

namespace Cube\Data\Database\Migration\Plans;

use Cube\Data\Bunch;
use Cube\Data\Database\Migration\SQLPlan;
use Cube\Data\Models\ModelField;

/**
 * SQLite alters little in place : changing a column, adding a foreign key or dropping a constrained
 * column rebuild the table (copy into a new table, drop, rename), the procedure SQLite documents.
 */
class SQLite extends SQLPlan
{
    public const REBUILD_PREFIX = '_cube_rebuild_';

    public static function supports(string $driver): bool
    {
        return 'sqlite' === strtolower($driver);
    }

    protected function getAutoIncrementClause(): string
    {
        return ' AUTOINCREMENT';
    }

    public function transaction(callable $callback): ?\Throwable
    {
        $enforced = $this->areForeignKeysEnforced();
        $this->database->exec('PRAGMA foreign_keys = OFF');

        try {
            return parent::transaction(function () use ($callback, $enforced) {
                $callback($this->database);

                if ($enforced)
                    $this->assertForeignKeysHold();
            });
        } finally {
            if ($enforced)
                $this->database->exec('PRAGMA foreign_keys = ON');
        }
    }

    public function create(string $table, array $fields = [], ?string $additionnalSQL = null)
    {
        if (!count($fields))
            $fields = [ModelField::id()];

        $fieldsExpressions = new Bunch();
        $relationsAndIndex = new Bunch();

        foreach ($fields as $field) {
            $fieldsExpressions->push("{$this->quote($field->name)} {$this->getModelFieldSQLQuery($field)}");

            if ($field->hasReference())
                $relationsAndIndex->push("FOREIGN KEY ({$this->quote($field->name)}) REFERENCES {$this->quote($field->getReferenceTable())}({$this->quote($field->referenceField)}) {$this->getOnDeleteClause($field->onDeleteBehavior)}");
        }

        $additionnalSQL = $additionnalSQL ? ", {$additionnalSQL}" : '';
        $this->database->exec("CREATE TABLE {$this->quote($table)} ( ".$fieldsExpressions->merge($relationsAndIndex)->join(',')." {$additionnalSQL} )");
    }

    public function addColumn(string $table, ModelField $field): void
    {
        $definition = trim("{$this->quote($field->name)} {$this->getModelFieldSQLQuery($field)} {$this->getReferencesClause($field)}");

        if (!$this->canAddColumnInPlace($field)) {
            $this->rebuild($table, fn (SQLiteTableDefinition $table) => $table->addColumn($definition));
            return;
        }

        $this->database->exec("ALTER TABLE {$this->quote($table)} ADD COLUMN {$definition}");
    }

    /** `ADD COLUMN` refuses these definitions, at least on a table holding rows */
    protected function canAddColumnInPlace(ModelField $field): bool
    {
        return !$field->isUnique
            && !$field->isPrimaryKey
            && !($field->hasDefault && $field->defaultIsExpression)
            && ($field->nullable || $field->hasDefault)
            && !($field->hasReference() && null !== $field->default);
    }

    /** Like MySQL's MODIFY, the column keeps its uniqueness and its foreign key when the new field does not repeat them */
    public function alterColumn(string $table, string $field, ModelField $newProperties)
    {
        $this->assertFieldExists($table, $field);

        if ($field !== $newProperties->name)
            $this->renameField($table, $field, $newProperties->name);

        $foreignKeys = $this->getForeignKeyConstraints($table, $newProperties->name);

        $this->rebuild($table, function (SQLiteTableDefinition $table) use ($newProperties, $foreignKeys) {
            $column = $newProperties->name;
            $current = $table->getColumn($column);
            $definition = "{$this->quote($column)} {$this->getModelFieldSQLQuery($newProperties)}";

            if (!$newProperties->isUnique && !$newProperties->isPrimaryKey && SQLiteTableDefinition::hasKeyword($current, 'UNIQUE'))
                $definition .= ' UNIQUE';

            $table->replaceColumn($column, $definition);

            if (!SQLiteTableDefinition::hasKeyword($current, 'REFERENCES'))
                return;
            foreach ($foreignKeys as $foreignKey)
                $table->addConstraint($foreignKey);
        });
    }

    public function addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey, ?string $deleteBehavior = null)
    {
        $this->assertFieldExists($table, $field);

        $constraint = trim("FOREIGN KEY ({$this->quote($field)}) REFERENCES {$this->quote($foreignTable)}({$this->quote($foreignKey)}) {$this->getOnDeleteClause($deleteBehavior)}");

        $this->rebuild($table, fn (SQLiteTableDefinition $table) => $table->addConstraint($constraint));
    }

    /** SQLite's `DROP COLUMN` refuses a unique or indexed column : the table is rebuilt without it, and its indexes dropped */
    public function dropColumn(string $table, string $field): void
    {
        $this->assertFieldExists($table, $field);

        $this->rebuild($table, fn (SQLiteTableDefinition $table) => $table->removeColumn($field), $field);
    }

    /** SQLite has no `DROP CONSTRAINT` : the name is looked for among the indexes, then the named table constraints */
    public function dropConstraint(string $table, string $constraintName): void
    {
        $this->assertTableExists($table);

        if (count($this->database->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = {} AND name = {}", [$table, $constraintName]))) {
            $this->database->exec("DROP INDEX {$this->quote($constraintName)}");
            return;
        }

        if (!$this->getTableDefinition($table)->removeNamedConstraint($constraintName))
            throw new \RuntimeException("Could not find the [{$constraintName}] constraint on the [{$table}] table");

        $this->rebuild($table, fn (SQLiteTableDefinition $table) => $table->removeNamedConstraint($constraintName));
    }

    /**
     * @param \Closure(SQLiteTableDefinition):mixed $transform     edits the definitions of the table
     * @param ?string                               $droppedColumn the indexes naming it are not recreated
     */
    protected function rebuild(string $table, \Closure $transform, ?string $droppedColumn = null): void
    {
        $this->assertTableExists($table);

        if (!$this->database->getConnection()->inTransaction()) {
            if ($error = $this->transaction(fn () => $this->copyTable($table, $transform, $droppedColumn)))
                throw $error;
            return;
        }

        if ($this->areForeignKeysEnforced())
            throw new \RuntimeException("Cannot rebuild the [{$table}] table in a transaction while foreign keys are enforced : SQLite would cascade its deletion to the rows referencing it. Run it in a migration, or outside of any transaction");

        $this->copyTable($table, $transform, $droppedColumn);
    }

    /** @param \Closure(SQLiteTableDefinition):mixed $transform */
    protected function copyTable(string $table, \Closure $transform, ?string $droppedColumn): void
    {
        $definition = $this->getTableDefinition($table);
        $transform($definition);

        $temporary = self::REBUILD_PREFIX.$table;
        $dependents = $this->database->query("SELECT type, sql FROM sqlite_master WHERE type IN ('index', 'trigger') AND tbl_name = {} AND sql IS NOT NULL", [$table]);
        $sequence = $this->getSequence($table);
        $oldColumns = $this->listStoredColumns($table);

        $this->database->exec($definition->toSQL($this->quote($temporary)));

        $columns = Bunch::wrap($this->listStoredColumns($temporary))
            ->filter(fn (string $column) => in_array($column, $oldColumns))
            ->map(fn (string $column) => $this->quote($column))
            ->join(', ')
        ;

        if ($columns)
            $this->database->exec("INSERT INTO {$this->quote($temporary)} ({$columns}) SELECT {$columns} FROM {$this->quote($table)}");

        $this->database->exec("DROP TABLE {$this->quote($table)}");
        $this->renameWithoutRewritingReferences($temporary, $table);

        foreach ($dependents as ['type' => $type, 'sql' => $sql]) {
            if ('index' === $type && $droppedColumn && SQLiteTableDefinition::mentions($sql, $droppedColumn))
                continue;
            $this->database->exec($sql);
        }

        if (null !== $sequence)
            $this->restoreSequence($table, $sequence);
    }

    /**
     * Out of legacy mode, a rename checks every view and trigger against the schema, where the
     * table they read is missing until the rename completes.
     */
    protected function renameWithoutRewritingReferences(string $from, string $to): void
    {
        $legacy = (int) $this->database->query('PRAGMA legacy_alter_table')[0]['legacy_alter_table'];
        $this->database->exec('PRAGMA legacy_alter_table = ON');

        try {
            $this->renameTable($from, $to);
        } finally {
            $this->database->exec("PRAGMA legacy_alter_table = {$legacy}");
        }
    }

    protected function getTableDefinition(string $table): SQLiteTableDefinition
    {
        $sql = $this->database->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = {}", [$table])[0]['sql']
            ?? throw new \RuntimeException("Could not find the [{$table}] table in the database");

        return SQLiteTableDefinition::fromSQL($sql);
    }

    /** @return string[] the columns holding data, generated ones left out */
    protected function listStoredColumns(string $table): array
    {
        return Bunch::fromQuery('SELECT name FROM pragma_table_xinfo({}) WHERE hidden = 0', [$table], $this->database)
            ->key('name')
            ->get()
        ;
    }

    /** @return string[] the foreign keys starting from `$column`, written as table constraints */
    protected function getForeignKeyConstraints(string $table, string $column): array
    {
        return Bunch::fromQuery('SELECT * FROM pragma_foreign_key_list({}) WHERE "from" = {} ORDER BY id, seq', [$table, $column], $this->database)
            ->map(fn (array $foreignKey) => trim(
                "FOREIGN KEY ({$this->quote($column)}) REFERENCES {$this->quote($foreignKey['table'])}"
                .(null === $foreignKey['to'] ? '' : "({$this->quote($foreignKey['to'])})")
                ." ON UPDATE {$foreignKey['on_update']} ON DELETE {$foreignKey['on_delete']}"
            ))
            ->get()
        ;
    }

    protected function getReferencesClause(ModelField $field): string
    {
        if (!$field->hasReference())
            return '';
        return "REFERENCES {$this->quote($field->getReferenceTable())}({$this->quote($field->referenceField)}) {$this->getOnDeleteClause($field->onDeleteBehavior)}";
    }

    /** Copying the rows sets the sequence to the highest id left, an id deleted above it would be given again */
    protected function getSequence(string $table): ?int
    {
        if (!$this->database->hasTable('sqlite_sequence'))
            return null;
        $sequence = $this->database->query('SELECT seq FROM sqlite_sequence WHERE name = {}', [$table])[0]['seq'] ?? null;

        return null === $sequence ? null : (int) $sequence;
    }

    protected function restoreSequence(string $table, int $sequence): void
    {
        if ($this->database->exec('UPDATE sqlite_sequence SET seq = {} WHERE name = {}', [$sequence, $table]))
            return;
        $this->database->exec('INSERT INTO sqlite_sequence (name, seq) VALUES ({}, {})', [$table, $sequence]);
    }

    protected function areForeignKeysEnforced(): bool
    {
        return 1 === (int) ($this->database->query('PRAGMA foreign_keys')[0]['foreign_keys'] ?? 0);
    }

    protected function assertForeignKeysHold(): void
    {
        $violations = $this->database->query('PRAGMA foreign_key_check');
        if (!count($violations))
            return;
        $first = $violations[0];

        throw new \RuntimeException(
            count($violations)." row(s) reference a missing row, the first one being [{$first['table']}] row [{$first['rowid']}] towards the [{$first['parent']}] table"
        );
    }
}
