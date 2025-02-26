<?php

namespace Cube\Tests\Units\Data\Sample;

use Cube\Data\DataToObject;

class Album extends DataToObject
{
    public function __construct(
        public string $name,
        public Artist $artist
    ) {}
}
