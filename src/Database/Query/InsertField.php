<?php

namespace YonisSavary\Cube\Database\Query;

class InsertField
{
    public function __construct(
        public readonly array $fields
    )
    {}
}