<?php

namespace YonisSavary\Cube\Database\Query;

use InvalidArgumentException;

class Order
{
    public readonly string $type;

    public function __construct(
        public readonly string $fieldOrAlias,
        string $type="DESC",
        public readonly ?string $table=null,
    )
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ["DESC", "ASC"]))
            throw new InvalidArgumentException("SQL Order type must be either 'DESC' or 'ASC'");

        $this->type = $type;
    }
}