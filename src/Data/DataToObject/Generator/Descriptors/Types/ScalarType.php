<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Types;

readonly class ScalarType extends DtoType
{
    /**
     * @param list<bool|float|int|string> $enum
     */
    public function __construct(
        public Scalar $scalar,
        public array $enum = [],
    ) {}
}
