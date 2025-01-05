<?php

namespace YonisSavary\Cube\Database\Query;

class FieldCondition
{
    public function __construct(
        public readonly ?string $table=null,
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $expression
    )
    {}
}