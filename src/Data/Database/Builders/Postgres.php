<?php

namespace Cube\Data\Database\Builders;

use Cube\Data\Database\DatabaseDriver;

class Postgres extends QueryBuilder
{
    public static function supports(string $pdoDriver): bool
    {
        return DatabaseDriver::Postgres->value === $pdoDriver;
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
