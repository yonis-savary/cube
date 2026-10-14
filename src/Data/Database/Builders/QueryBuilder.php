<?php

namespace Cube\Data\Database\Builders;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Data\Database\Query\Field;
use Cube\Data\Database\Query\FieldComparaison;
use Cube\Data\Database\Query\FieldCondition;
use Cube\Data\Database\Query\Join;
use Cube\Data\Database\Query\Order;
use Cube\Data\Database\Query\QueryBase;
use Cube\Data\Database\Query\RawCondition;
use Cube\Data\Database\Query\UpdateField;
use Cube\Data\Models\Model;
use Exception;
use Throwable;

abstract class QueryBuilder
{
    protected Database $database;
    protected Query $query;

    abstract public static function supports(string $pdoDriver): bool;

    /** @return string[] */
    abstract public function getIdentifierDelimiters(): array;

    abstract public function getUnboundedLimit(): string;

    public function quote(string $identifier): string
    {
        $delimiter = $this->getIdentifierDelimiters()[0];

        return $delimiter.str_replace($delimiter, $delimiter.$delimiter, $identifier).$delimiter;
    }

    protected function getRowIdentifier(): ?string
    {
        return null;
    }

    public function prepareQuotedString(mixed $value, string $delimiter, Database $database): string
    {
        if (!in_array($delimiter, $this->getIdentifierDelimiters()))
            return $this->prepareString($value, false, $database);

        return str_replace($delimiter, $delimiter.$delimiter, (string) $value);
    }

    public function prepareString(mixed $value, bool $quote, Database $database): string
    {
        if ($value instanceof Model) {
            return $this->prepareString($value->id(), $quote, $database);
        }

        if ($value instanceof \DateTimeInterface) {
            return $this->prepareString($value->format('Y-m-d H:i:s'), $quote, $database);
        }

        if (is_array($value)) {
            return "(". Bunch::wrap($value)->map(fn($v) => $this->prepareString($v, true, $database))->join(',') . ")";
        }

        if (is_object($value) && enum_exists($value::class)) {
            return $this->prepareString($value->value, $quote, $database);
        }

        if (null === $value) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $this->prepareString($value ? '1' : '0', $quote, $database);
        }

        $value = $database->getConnection()->quote($value, \PDO::PARAM_STR);

        return $quote ? $value : substr($value, 1, -1);
    }

    public function getTable(string $table): string
    {
        return $this->quote($table);
    }

    public function getUpdateTables(): string
    {
        return
            Bunch::wrap($this->query->base->table)
                ->push(
                    ...Bunch::wrap($this->query->joins)
                        ->map(fn (Join $join) => $join->tableToJoin)
                        ->get()
                )
                ->map(fn ($table) => $this->getTable($table))
                ->join(', ')
        ;
    }

    public function getField(Field $fieldObject): string
    {
        $expression = $fieldObject->expression;
        $table = $fieldObject->table;
        $field = $fieldObject->field;
        $alias = $fieldObject->alias;

        $fieldExpression = $table ? $this->getTable($table).".{$field}" : $field;

        return ($expression ?? $fieldExpression).($alias ? ' AS '.$this->quote($alias) : '');
    }

    public function getSelectFields(): string
    {
        return
            Bunch::wrap($this->query->selectFields)
                ->map(fn (Field $field) => $this->getField($field))
                ->join(",\n ")
        ;
    }

    public function getInsertFields(): string
    {
        return
            '('
                .Bunch::wrap($this->query->insertFields->fields)
                    ->map(fn ($field) => $this->quote($field))
                    ->join(', ')
            .')'
        ;
    }

    public function getInsertValues(): string
    {
        return Bunch::wrap($this->query->insertValues)
            ->map(fn ($values) => $this->prepareString($values->values, false, $this->database))
            ->join(', ')
        ;
    }

    public function getUpdates(): string
    {
        return Bunch::wrap($this->query->updateFields)
            ->map(fn (UpdateField $field) => sprintf('%s = %s', $field->field, $this->getSQLValue($field->newValue)))
            ->join(', ')
        ;
    }

    public function getSQLValue(mixed $value): string
    {
        return $value instanceof Query
            ? "(" . (new static())->build($value, $this->database) . ")"
            : $this->database->build('{}', [$value]);
    }

    public function getFieldComparaison(FieldComparaison $condition)
    {
        return sprintf(
            '%s.%s %s %s.%s',
            $this->getTable($condition->source),
            $condition->sourceField,
            $condition->operator,
            $this->getTable($condition->target),
            $condition->targetField,
        );
    }

    public function getConditions(array $conditions): string
    {
        $body = $this->getConditionsBody($conditions);

        return $body ? "WHERE $body" : '';
    }

    /**
     * @param array<array|FieldComparaison|FieldCondition|RawCondition> $conditions
     */
    protected function getConditionsBody(array $conditions): string
    {
        $body = '';
        $operator = 'AND';

        foreach ($conditions as $condition)
        {
            if (is_string($condition)) {
                $operator = $condition;
                continue;
            }

            if (!$stringCondition = $this->getCondition($condition))
                continue;

            $body .= $body ? " $operator $stringCondition" : $stringCondition;
            $operator = 'AND';
        }

        return $body;
    }

    protected function getCondition(array|FieldComparaison|FieldCondition|RawCondition $condition): string
    {
        if (is_array($condition))
            return ($group = $this->getConditionsBody($condition)) ? "($group)" : '';

        return match (true) {
            $condition instanceof FieldComparaison => $this->getFieldComparaison($condition),
            $condition instanceof RawCondition => $condition->expression,
            $condition instanceof FieldCondition => sprintf(
                '%s%s %s %s%s',
                $condition->table ? $this->getTable($condition->table).'.' : '',
                $condition->field,
                $condition->operator,
                $this->getSQLValue($condition->expression),
                $this->getLikeEscape($condition->operator),
            ),
        };
    }

    protected function getLikeEscape(string $operator): string
    {
        return str_contains(strtoupper($operator), 'LIKE')
            ? ' ESCAPE '.$this->getSQLValue('\\')
            : '';
    }

    public function getUpdateConditions(): string
    {
        $baseConditions = $this->getConditions($this->query->conditions);

        $updateConditions = count($this->query->joins)
            ? '('
                .Bunch::wrap($this->query->joins)
                    ->map(fn (Join $join) => $this->getFieldComparaison($join->condition))
                    ->join(") \n AND \n (")
            .')'
        : '';

        if ($baseConditions && $updateConditions) {
            return "{$baseConditions} AND {$updateConditions}";
        }
        if ($baseConditions) {
            return $baseConditions;
        }
        if ($updateConditions) {
            return "WHERE {$updateConditions}";
        }

        return '';
    }

    public function getOrders(): string
    {
        return count($this->query->orders)
                ? 'ORDER BY '
                .Bunch::wrap($this->query->orders)
                    ->map(function (Order $order) {
                        return
                            $order->table
                                ? sprintf('%s.%s %s', $this->getTable($order->table), $order->fieldOrAlias, $order->type)
                                : sprintf('%s %s', $this->quote($order->fieldOrAlias), $order->type);
                    })
                    ->join(', ')
            : '';
    }

    public function getLimit(): string
    {
        if (!$limit = $this->query->limit) {
            return '';
        }

        $offset = $limit->offset;
        $limit = $limit->limit;

        return
            'LIMIT '.($limit ?? $this->getUnboundedLimit())
            .($offset ? (' OFFSET '.$offset) : '');
    }

    public function build(Query $query, Database $database): string
    {
        $this->query = $query;
        $this->database = $database;
        $base = $query->base->type;

        switch ($base) {
            case QueryBase::INSERT: return $this->buildInsert();
            case QueryBase::SELECT: return $this->buildSelect();
            case QueryBase::UPDATE: return $this->buildUpdate();
            case QueryBase::DELETE: return $this->buildDelete();
            default: throw new Exception("Unsupported query mode $base");
        }
    }

    public function count(Query $query, Database $database): int
    {
        $baseQuery = $this->build($query, $database);

        $wrappedQuery = "SELECT COUNT(*) AS __count FROM ({$baseQuery}) AS __base";

        return $database->query($wrappedQuery)[0]['__count'];
    }

    protected function getJoins(): string
    {
        return Bunch::wrap($this->query->joins)
            ->map(function (Join $join) {
                return sprintf(
                    '%s JOIN %s %s %s',
                    $join->type,
                    $this->getTable($join->tableToJoin),
                    $join->alias ? ' AS '.$this->quote($join->alias) : '',
                    $join->condition ? ' ON '.$this->getFieldComparaison($join->condition) : ''
                );
            })
            ->join("\n")
        ;
    }

    protected function buildInsert(): string
    {
        return sprintf(
            "INSERT INTO %s %s \n VALUES %s",
            $this->getTable($this->query->base->table),
            $this->getInsertFields(),
            $this->getInsertValues()
        );
    }

    protected function buildSelect(): string
    {
        return sprintf(
            "SELECT %s \nFROM %s \n%s \n%s \n%s \n%s",
            $this->getSelectFields(),
            $this->getTable($this->query->base->table),
            $this->getJoins(),
            $this->getConditions($this->query->conditions),
            $this->getOrders(),
            $this->getLimit()
        );
    }

    protected function buildUpdate(): string
    {
        return sprintf(
            "UPDATE %s \nSET %s \n%s \n%s \n%s",
            $this->getUpdateTables(),
            $this->getUpdates(),
            $this->getUpdateConditions(),
            $this->getOrders(),
            $this->getLimit()
        );
    }

    protected function buildDelete(): string
    {
        $table = $this->getTable($this->query->base->table);

        if (!$rowIdentifier = $this->getRowIdentifier()) {
            return sprintf(
                "DELETE FROM %s \n%s \n%s \n%s",
                $table,
                $this->getConditions($this->query->conditions),
                $this->getOrders(),
                $this->getLimit()
            );
        }

        if (!$this->query->limit)
            return sprintf("DELETE FROM %s \n%s", $table, $this->getConditions($this->query->conditions));

        // Without DELETE ... LIMIT, the rows are picked through their physical identifier instead
        return sprintf(
            "DELETE FROM %s WHERE %s IN (SELECT %s FROM %s \n%s \n%s \n%s)",
            $table,
            $rowIdentifier,
            $rowIdentifier,
            $table,
            $this->getConditions($this->query->conditions),
            $this->getOrders(),
            $this->getLimit()
        );
    }

    /**
     * @param \Closure(Database) $callback
     */
    public function transaction(callable $callback, Database $database): ?Throwable
    {
        try
        {
            $database->exec('BEGIN');
            $callback($database);
            $database->exec('COMMIT');
            return null;
        }
        catch (Throwable $thrown)
        {
            $database->exec('ROLLBACK');
            return $thrown;
        }
    }

    public function hasTable(string $table, Database $database): bool
    {
        // The table is probed through a failing query, which only fails when executed : dry-run mode is lifted meanwhile
        return $database->dryRun(function() use ($database, $table) {
            try {
                $database->query("SELECT 1 FROM {$this->quote($table)} LIMIT 1");
                return true;
            } catch (\PDOException) {
                return false;
            }
        }, false);
    }

    public function hasField(string $table, string $field, Database $database): bool
    {
        // The field is probed through a failing query, which only fails when executed : dry-run mode is lifted meanwhile
        return $database->dryRun(function() use ($database, $table, $field) {
            try {
                $database->query("SELECT {$this->quote($field)} FROM {$this->quote($table)} LIMIT 1");
                return true;
            } catch (\PDOException) {
                return false;
            }
        }, false);
    }
}
