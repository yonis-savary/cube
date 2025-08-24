<?php

namespace Cube\Data\Database\Query;

class RawCondition
{
    public function __construct(
        public readonly string $expression
    ) {}
}
