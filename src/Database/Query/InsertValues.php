<?php

namespace Cube\Database\Query;

class InsertValues
{
    public function __construct(
        public readonly array $values
    ) {}
}
