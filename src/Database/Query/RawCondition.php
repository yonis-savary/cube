<?php

namespace Cube\Database\Query;

class RawCondition
{
    public function __construct(
        public readonly string $expression
    )
    {}
}