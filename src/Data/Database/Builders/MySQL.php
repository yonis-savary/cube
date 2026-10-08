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
use Cube\Utils\Text;
use Exception;
use Throwable;

class MySQL extends QueryBuilder
{
    protected Database $database;
    protected Query $query;

    public static function supports(string $pdoDriver): bool
    {
        return $pdoDriver === 'mysql';
    }

    public function getIdentifierDelimiters(): array
    {
        return ['`'];
    }

    public function getUnboundedLimit(): string
    {
        return '18446744073709551615';
    }

    public function getTable(string $table): string
    {
        return "`{$table}`";
    }

    public function getUpdateTables(): string
    {
        return
            Bunch::of($this->query->base->table)
                ->push(
                    ...Bunch::of($this->query->joins)
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

        $fieldExpression = $table ? "`{$table}`.{$field}" : $field;

        return ($expression ?? $fieldExpression).($alias ? " AS `{$alias}`" : '');
    }

    public function getSelectFields(): string
    {
        return
            Bunch::of($this->query->selectFields)
                ->map(fn (Field $field) => $this->getField($field))
                ->join(",\n ")
        ;
    }

    public function getInsertFields(): string
    {
        return
            '('
                .Bunch::of($this->query->insertFields->fields)
                    ->map(fn ($field) => sprintf('`%s`', $field))
                    ->join(', ')
            .')'
        ;
    }

    public function getInsertValues(): string
    {
        return Bunch::of($this->query->insertValues)
            ->map(fn ($values) => $this->prepareString($values->values, false, $this->database))
            ->join(', ')
        ;
    }

    public function getUpdates(): string
    {
        return Bunch::of($this->query->updateFields)
            ->map(fn (UpdateField $field) => sprintf('`%s`.%s = %s', $field->table, $field->field, $this->getSQLValue($field->newValue)))
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
            '`%s`.%s %s `%s`.%s',
            $condition->source,
            $condition->sourceField,
            $condition->operator,
            $condition->target,
            $condition->targetField,
        );
    }

    public function getQualifiedTable(string $table): string
    {
        return "`{$table}`";
    }

    public function getConditions(array $conditions): string
    {
        $body = $this->getConditionsBody($conditions);

        return $body ? "WHERE $body" : '';
    }

    protected function getConditionsBody(array $conditions): string
    {
        $body = '';
        for ($i = 0; $i < count($conditions); $i++)
        {
            $condition = $conditions[$i];
            if (is_string($condition))
                continue;

            if (!$stringCondition = $this->getCondition($condition))
                continue;

            $nextElement = $conditions[$i+1] ?? 'AND';
            if (!is_string($nextElement))
                $nextElement = 'AND';

            $body .= "$stringCondition $nextElement ";
        }

        $body = trim(Text::dontEndsWith(trim($body), 'OR'));
        return trim(Text::dontEndsWith($body, 'AND'));
    }

    protected function getCondition(array|FieldComparaison|FieldCondition|RawCondition $condition): string
    {
        if (is_array($condition))
            return ($group = $this->getConditionsBody($condition)) ? "($group)" : '';

        return match (true) {
            $condition instanceof FieldComparaison => $this->getFieldComparaison($condition),
            $condition instanceof RawCondition => $condition->expression,
            $condition instanceof FieldCondition => sprintf(
                '%s%s %s %s',
                $condition->table ? $this->getQualifiedTable($condition->table).'.' : '',
                $condition->field,
                $condition->operator,
                $this->getSQLValue($condition->expression),
            ),
        };
    }

    public function getUpdateConditions(): string
    {
        $baseConditions = $this->getConditions($this->query->conditions);

        $updateConditions = count($this->query->joins)
            ? '('
                .Bunch::of($this->query->joins)
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
                .Bunch::of($this->query->orders)
                    ->map(function (Order $order) {
                        return
                            $order->table
                                ? sprintf('`%s`.%s %s', $order->table, $order->fieldOrAlias, $order->type)
                                : sprintf('`%s` %s', $order->fieldOrAlias, $order->type);
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
        return Bunch::of($this->query->joins)
            ->map(function (Join $join) {
                return sprintf(
                    '%s JOIN `%s` %s %s',
                    $join->type,
                    $join->tableToJoin,
                    $join->alias ? ' AS `'.$join->alias.'`' : '',
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
        return sprintf(
            "DELETE FROM %s \n%s \n%s \n%s",
            $this->getTable($this->query->base->table),
            $this->getConditions($this->query->conditions),
            $this->getOrders(),
            $this->getLimit()
        );
    }

    public function transaction(callable $callback, Database $database): ?Throwable
    {
        try
        {
            $database->exec('START TRANSACTION');
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
        /**
         * SQL (MySQL/Postgres) Specifics: our hasTable is based on a try-catch behavior
         * The exception we wait for cannot be raised if we don't fetch for results (if dryrun mode is enabled)
         * So we disable it just to test
         */
        return $database->dryRun(function() use ($database, $table) {
            try {
                $database->query("SELECT 1 FROM `{}` LIMIT 1", [$table]);
                return true;
            } catch (\PDOException) {
                return false;
            }
        }, false);
    }

    public function hasField(string $table, string $field, Database $database): bool
    {
        /**
         * SQL (MySQL/Postgres) Specifics: our hasTable is based on a try-catch behavior
         * The exception we wait for cannot be raised if we don't fetch for results (if dryrun mode is enabled)
         * So we disable it just to test
         */
        return $database->dryRun(function() use ($database, $table, $field) {
            try {
                $database->query("SELECT `{}` FROM `{}` LIMIT 1", [$field, $table]);

                return true;
            } catch (\PDOException) {
                return false;
            }
        }, false);
    }
}
