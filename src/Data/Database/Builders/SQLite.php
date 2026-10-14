<?php

namespace Cube\Data\Database\Builders;

class SQLite extends QueryBuilder
{
    public static function supports(string $pdoDriver): bool
    {
        return $pdoDriver === 'sqlite';
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
