<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Types;

readonly class ListType extends DtoType
{
    public function __construct(
        public DtoType $item,
    ) {}
}
