<?php

namespace Cube\Database\Builders;

use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Database\Query;

abstract class QueryBuilder
{
    /** @return string|string[] */
    abstract public function getSupportedPDODriver(): array|string;

    abstract public function build(Query $query, Database $database): string;

    abstract public function count(Query $query, Database $database): int;

    public function supports(string $pdoDriver): bool
    {
        return Bunch::of($this->getSupportedPDODriver())->has($pdoDriver);
    }
}
