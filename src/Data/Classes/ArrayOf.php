<?php

namespace Cube\Data\Classes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class ArrayOf
{
    public function __construct(
        public string $dataToObjectClass
    ) {}
}
