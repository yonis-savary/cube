<?php

namespace Cube\Database\Builders;

use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Database\Query;
use Cube\Database\Query\Field;
use Cube\Database\Query\FieldComparaison;
use Cube\Database\Query\FieldCondition;
use Cube\Database\Query\InsertValues;
use Cube\Database\Query\Join;
use Cube\Database\Query\Order;
use Cube\Database\Query\QueryBase;
use Cube\Database\Query\RawCondition;
use Cube\Database\Query\UpdateField;

class MySQL extends QueryBuilder
{
    protected Database $database;
    protected Query $query;

    public function getSupportedPDODriver(): string|array
    {
        return ["mysql"];
    }

    public function getTable(string $table): string
    {
        return "`$table`";
    }

    public function getUpdateTables(): string
    {
        return
            Bunch::of($this->query->base->table)
            ->push(
                ...Bunch::of($this->query->joins)
                ->map(fn(Join $join) => $join->tableToJoin)
                ->get()
            )
            ->map(fn($table) => $this->getTable($table))
            ->join(", ");
    }

    public function getField(Field $fieldObject): string
    {
        $expression = $fieldObject->expression;
        $table = $fieldObject->table;
        $field = $fieldObject->field;
        $alias = $fieldObject->alias;

        $fieldExpression = $table ? "`$table`.$field" : $field;

        return ($expression ?? $fieldExpression) . ($alias ? " AS `$alias`": '');
    }

    public function getSelectFields(): string
    {
        return
            Bunch::of($this->query->selectFields)
            ->map(fn(Field $field) => $this->getField($field))
            ->join(",\n ");
    }

    public function getInsertFields(): string
    {
        return
            "(" .
                Bunch::of($this->query->insertFields->fields)
                ->map(fn($field) => sprintf("`%s`", $field))
                ->join(", ")
            . ")";
    }

    public function getInsertValues(): string
    {
        return
            Bunch::of($this->query->insertValues)
            ->map(fn(InsertValues $values) =>
                $this->database->build(
                    "(". Bunch::fill(count($values->values), "{}")->join(", ") . ")",
                    $values->values
                )
            )
            ->join(", ");
    }

    public function getUpdates(): string
    {
        return
            Bunch::of($this->query->updateFields)
            ->map(function(UpdateField $field) {
                return sprintf("`%s`.%s = %s",
                    $field->table,
                    $field->field,
                    $this->getSQLValue($field->newValue)
                );
            })
            ->join(", ");
    }

    public function getSQLValue(mixed $value): string
    {
        return $this->database->build("{}", [$value]);
    }

    public function getFieldComparaison(FieldComparaison $condition)
    {
        return sprintf("`%s`.%s %s `%s`.%s",
            $condition->source,
            $condition->sourceField,
            $condition->operator,
            $condition->target,
            $condition->targetField,
        );
    }

    public function getConditions(): string
    {
        return count($this->query->conditions) ? "WHERE (" .
            Bunch::of($this->query->conditions)
            ->map(function(FieldComparaison|FieldCondition|RawCondition $condition) {
                if ($condition instanceof FieldComparaison)
                    return $this->getFieldComparaison($condition);
                else if ($condition instanceof FieldCondition)
                    return sprintf("%s%s %s %s",
                        $condition->table ? "`".$condition->table."`." : '',
                        $condition->field,
                        $condition->operator,
                        $this->getSQLValue($condition->expression),
                    );
                else if ($condition instanceof RawCondition)
                    return $condition->expression;
            })
            ->join(") \n AND \n (")
        . ")": '';
    }

    public function getUpdateConditions(): string
    {
        $baseConditions = $this->getConditions();

        $updateConditions = count($this->query->joins) ?
            "(" .
                Bunch::of($this->query->joins)
                ->map(fn(Join $join) => $this->getFieldComparaison($join->condition))
                ->join(") \n AND \n (")
            .")"
        : "";

        if ($baseConditions && $updateConditions)
            return "$baseConditions AND $updateConditions";
        else if ($baseConditions)
            return $baseConditions;
        else if ($updateConditions)
            return $updateConditions;
        else
            return "";
    }

    protected function getJoins(): string
    {
        return Bunch::of($this->query->joins)
            ->map(function(Join $join) {
                return sprintf("%s JOIN `%s` %s %s",
                    $join->type,
                    $join->tableToJoin,
                    $join->alias ? " AS `".$join->alias."`": '',
                    $join->condition ? " ON " . $this->getFieldComparaison($join->condition): ''
                );
            })
            ->join("\n")
            ;
    }

    public function getOrders(): string
    {
        return count($this->query->orders) ?
                "ORDER BY " .
                Bunch::of($this->query->orders)
                ->map(function(Order $order) {
                    return
                        $order->table ?
                            sprintf("`%s`.%s %s", $order->table, $order->fieldOrAlias, $order->type):
                            sprintf("`%s` %s", $order->fieldOrAlias, $order->type);
                })
                ->join(", ")
            :'';
    }

    public function getLimit(): string
    {
        if (! $limit = $this->query->limit)
            return "";

        $offset = $limit->offset;
        $limit = $limit->limit;

        return
            ($limit ? ("LIMIT " . $limit) : '').
            ($offset ? (" OFFSET " . $offset) : '');
    }

    protected function buildInsert(): string
    {
        return sprintf("INSERT INTO %s %s \n VALUES %s",
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
        return sprintf("DELETE FROM %s \n%s \n%s \n%s",
            $this->getTable($this->query->base->table),
            $this->getConditions(),
            $this->getOrders(),
            $this->getLimit()
        );
    }

    public function build(Query $query, Database $database): string
    {
        $this->query = $query;
        $this->database = $database;
        $base = $query->base->type;
        switch ($base)
        {
            case QueryBase::INSERT: return $this->buildInsert();
            case QueryBase::SELECT: return $this->buildSelect();
            case QueryBase::UPDATE: return $this->buildUpdate();
            case QueryBase::DELETE: return $this->buildDelete();
        }
    }

    public function count(Query $query, Database $database): int
    {
        $baseQuery = $this->build($query, $database);

        $wrappedQuery = "SELECT COUNT(*) AS __count FROM ($baseQuery) AS __base";

        return $database->query($wrappedQuery)[0]['__count'];
    }
}