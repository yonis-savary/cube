<?php

namespace Cube\Data\DataToObject\Generator\Descriptors;

use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;

readonly class DtoClass
{
    /**
     * @param string        $namespace  relative to the namespace the classes are generated into
     * @param DtoProperty[] $properties
     * @param ?ClassType    $parent     a class with a parent only extends it, without properties of its own
     */
    public function __construct(
        public string $namespace,
        public string $name,
        public array $properties = [],
        public ?ClassType $parent = null,
        public DtoDirection $direction = DtoDirection::Output,
    ) {}

    public function toType(): ClassType
    {
        return new ClassType($this->namespace, $this->name);
    }
}
