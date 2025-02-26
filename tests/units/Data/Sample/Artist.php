<?php

namespace Cube\Tests\Units\Data\Sample;

use Cube\Data\DataToObject;

class Artist extends DataToObject
{
    public function __construct(
        public string $name
    ) {}
}
