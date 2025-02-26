<?php

namespace Cube\Data\Classes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class BunchOf
{
    public function __construct(
        public string $dataToObjectClass
    ) {}
}
