<?php

namespace Cube\Data\Database\Builders;

use Cube\Data\Bunch;
use Cube\Data\Database\Query\UpdateField;

class MySQL extends QueryBuilder
{
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

    public function getUpdates(): string
    {
        return Bunch::wrap($this->query->updateFields)
            ->map(fn (UpdateField $field) => sprintf('%s.%s = %s', $this->getTable($field->table), $field->field, $this->getSQLValue($field->newValue)))
            ->join(', ')
        ;
    }
}
