<?php

namespace YonisSavary\Cube\Database\Query;

class Join
{
    public function __construct(
        public readonly string $type="LEFT",
        public readonly string $tableToJoin,
        public readonly ?string $alias=null,
        public readonly ?FieldComparaison $condition=null
    )
    {}
}