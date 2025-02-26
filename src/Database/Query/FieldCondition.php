<?php

namespace Cube\Database\Query;

class FieldCondition
{
    public function __construct(
        public readonly string $table,
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $expression
    ) {}
}
