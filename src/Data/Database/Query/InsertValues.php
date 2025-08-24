<?php

namespace Cube\Data\Database\Query;

class InsertValues
{
    public function __construct(
        public readonly array $values
    ) {}
}
