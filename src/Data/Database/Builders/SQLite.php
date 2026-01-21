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
use Cube\Env\Logger\Logger;
use Cube\Utils\Text;
use Exception;
use Throwable;

class SQLite extends MySQL
{
    protected Database $database;
    protected Query $query;

    public function supports(string $pdoDriver): bool
    {
        return $pdoDriver === 'sqlite';
    }

    public function getTable(string $table): string
    {
        return $table;
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

        return ($expression ?? $fieldExpression).(($alias && (!str_contains($alias, '.'))) ? " AS `{$alias}`" : '');
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
                    ->map(fn ($field) => sprintf('%s', $field))
                    ->join(', ')
            .')'
        ;
    }

    public function getInsertValues(): string
    {
        return Bunch::of($this->query->insertValues)
            ->map(fn ($values) => $this->prepareString($values->values))
            ->join(', ')
        ;
    }

    public function getUpdates(): string
    {
        return
            Bunch::of($this->query->updateFields)
                ->map(function (UpdateField $field) {
                    return sprintf(
                        '%s = %s',
                        $field->field,
                        $this->getSQLValue($field->newValue)
                    );
                })
                ->join(', ')
        ;
    }

    public function getSQLValue(mixed $value): string
    {
        return $this->database->build('{}', [$value]);
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

    public function getConditions(): string
    {
        if (! $count = count($this->query->conditions))
            return '';

        $conditions = "";
        for ($i=0; $i < $count; $i++)
        {
            $condition = $this->query->conditions[$i];
            if (is_string($condition))
                continue;

            $nextElement = $this->query->conditions[$i+1] ?? 'AND';

            if (!is_string($nextElement)) 
                $nextElement = 'AND';

            if ($condition instanceof FieldComparaison) {
                $stringCondition = $this->getFieldComparaison($condition);
            }
            else if ($condition instanceof FieldCondition) {
                $stringCondition = sprintf(
                    '%s%s %s %s',
                    $condition->table ? '`'.$condition->table.'`.' : '',
                    $condition->field,
                    $condition->operator,
                    $this->getSQLValue($condition->expression),
                );
            }
            else if ($condition instanceof RawCondition) {
                $stringCondition = $condition->expression;
            }
            else
            {
                return '';
            }

            $conditions .= $stringCondition . " $nextElement ";
        }

        $fullCondition = trim("WHERE $conditions");
        $fullCondition = trim(Text::dontEndsWith($fullCondition, 'OR'));
        $fullCondition = trim(Text::dontEndsWith($fullCondition, 'AND'));

        return $fullCondition;
    }

    public function getUpdateConditions(): string
    {
        $baseConditions = $this->getConditions();

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
            return $updateConditions;
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
                                ? sprintf('%s.%s %s', $order->table, $order->fieldOrAlias, $order->type)
                                : sprintf('%s %s', $order->fieldOrAlias, $order->type);
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
            ($limit ? ('LIMIT '.$limit) : '')
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

    protected function getJoins(): string
    {
        return Bunch::of($this->query->joins)
            ->map(function (Join $join) {
                return sprintf(
                    '%s JOIN %s %s %s',
                    $join->type,
                    $join->tableToJoin,
                    $join->alias && (!str_contains($join->alias, '.')) ? ' AS `'.$join->alias.'`' : '',
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
            $this->getConditions(),
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
        if ($this->query->limit) {
            Logger::getInstance()->warning('LIMIT statement for delete query is not supported by SQLite');
        }

        return sprintf(
            "DELETE FROM %s \n%s \n%s",
            $this->getTable($this->query->base->table),
            $this->getConditions(),
            $this->getOrders()
        );
    }

    public function transaction(callable $callback, Database $database): true|Throwable
    {
        try
        {
            $database->exec('BEGIN TRANSACTION');
            $callback($database);
            $database->exec('COMMIT');
            return true;
        }
        catch (Throwable $thrown)
        {
            $database->exec('ROLLBACK');
            return $thrown;
        }
    }
}
