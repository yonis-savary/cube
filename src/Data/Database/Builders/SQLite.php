<?php

namespace Cube\Data\Database\Builders;

use Cube\Data\Database\DatabaseDriver;

class SQLite extends QueryBuilder
{
    public static function supports(string $pdoDriver): bool
    {
        return DatabaseDriver::SQLite->value === $pdoDriver;
    }

    public function getIdentifierDelimiters(): array
    {
        return ['`', '"'];
    }

    public function getUnboundedLimit(): string
    {
        return '-1';
    }

    protected function getRowIdentifier(): ?string
    {
        return 'rowid';
    }
}
