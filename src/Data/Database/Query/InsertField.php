<?php

namespace Cube\Data\Database\Query;

class InsertField
{
    public function __construct(
        public readonly array $fields
    ) {}
}
