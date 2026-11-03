<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Types;

readonly class MapType extends DtoType
{
    public function __construct(
        public DtoType $value,
    ) {}
}
