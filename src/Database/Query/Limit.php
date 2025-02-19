<?php

namespace Cube\Database\Query;

use InvalidArgumentException;

class Limit
{
    public function __construct(
        public readonly ?int $limit=null,
        public readonly ?int $offset=null,
    )
    {
        if (!($limit || $offset))
            throw new InvalidArgumentException("Either \$limit or \$offset is needed");
    }
}