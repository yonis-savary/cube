<?php

namespace Cube\Database\Query;

class UpdateField
{
    public function __construct(
        public readonly string $table,
        public readonly string $field,
        public readonly mixed $newValue,
    )
    {}
}