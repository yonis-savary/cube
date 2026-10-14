<?php

namespace Cube\Data\Database\Builders;

class Postgres extends QueryBuilder
{
    public static function supports(string $pdoDriver): bool
    {
        return $pdoDriver === 'pgsql';
    }

    public function getIdentifierDelimiters(): array
    {
        return ['"'];
    }

    public function getUnboundedLimit(): string
    {
        return 'ALL';
    }

    protected function getRowIdentifier(): ?string
    {
        return 'ctid';
    }
}
