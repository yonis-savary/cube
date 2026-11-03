<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Types;

readonly class ClassType extends DtoType
{
    /**
     * @param string $namespace relative to the namespace the classes are generated into
     */
    public function __construct(
        public string $namespace,
        public string $name,
    ) {}
}
