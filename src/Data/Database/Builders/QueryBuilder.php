<?php

namespace Cube\Data\Database\Builders;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Data\Models\Model;
use Throwable;

abstract class QueryBuilder
{
    abstract public function build(Query $query, Database $database): string;

    abstract public function count(Query $query, Database $database): int;

    abstract public static function supports(string $pdoDriver): bool;

    /** @return string[] */
    abstract public function getIdentifierDelimiters(): array;

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
            return "(". Bunch::of($value)->map(fn($v) => $this->prepareString($v, true, $database))->join(',') . ")";
        }

        if (is_object($value) && enum_exists($value::class)) {
            return $this->prepareString($value->value, $quote, $database);
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

        $value = $database->getConnection()->quote($value, \PDO::PARAM_STR);

        return $quote ? $value : substr($value, 1, -1);
    }

    /**
     * @param \Closure(Database) $callback
     */
    abstract public function transaction(callable $callback, Database $database): ?Throwable;

    abstract public function hasTable(string $table, Database $database): bool;

    abstract public function hasField(string $table, string $field, Database $database): bool;
}
