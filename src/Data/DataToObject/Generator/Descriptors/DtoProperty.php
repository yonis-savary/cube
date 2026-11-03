<?php

namespace Cube\Data\DataToObject\Generator\Descriptors;

use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;

readonly class DtoProperty
{
    /**
     * @param string $key the key of the source data, the PHP name is derived from it
     */
    public function __construct(
        public string $key,
        public DtoType $type,
        public bool $nullable,
        public bool $required,
    ) {}
}
