<?php

namespace YonisSavary\Cube\Database\Query;

class RawCondition
{
    public function __construct(
        public readonly string $expression
    )
    {}
}