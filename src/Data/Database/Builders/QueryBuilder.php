<?php

namespace Cube\Data\Database\Builders;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Data\Models\Model;

abstract class QueryBuilder
{
    abstract public function build(Query $query, Database $database): string;

    abstract public function count(Query $query, Database $database): int;

    abstract public function supports(string $pdoDriver): bool;

    public function prepareString(mixed $value, $quote = false): string
    {
        if ($value instanceof Model) {
            return $this->prepareString($value->id(), $quote);
        }

        if (is_array($value)) {
            return "(". Bunch::of($value)->map(fn($v) => $this->prepareString($v, true))->join(',') . ")";
        }

        if (is_object($value) && enum_exists($value::class)) {
            return $this->prepareString($value->value);
        }

        if (null === $value) {
            return 'NULL';
        }

        if (true === $value) {
            return 'TRUE';
        }

        if (false === $value) {
            return 'FALSE';
        }

        $value = preg_replace('/([\'\\\])/', '$1$1', $value);

        return $quote ? "'{$value}'" : $value;
    }
}
