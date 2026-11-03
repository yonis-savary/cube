<?php

namespace Cube\Data\DataToObject\Generator\Sources;

use Cube\Data\DataToObject\Generator\Descriptors\DtoClass;
use Cube\Data\DataToObject\Generator\Descriptors\DtoProperty;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ListType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\MixedType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\Scalar;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ScalarType;
use Cube\Data\DataToObject\Generator\DtoNames;
use Cube\Utils\Utils;

/**
 * Infers the classes from one example payload : every field is nullable, nested objects get their own
 * sub-namespace, and a list is typed after its first element
 */
class SampleSource implements DtoSource
{
    /** @var DtoClass[] */
    protected array $classes = [];

    /**
     * @param array<string,mixed> $sample
     */
    public function __construct(
        protected string $rootName,
        protected array $sample,
    ) {}

    public function toClasses(): array
    {
        $this->classes = [];
        $this->addClass('', $this->rootName, $this->sample);

        return $this->classes;
    }

    /**
     * @param array<string,mixed> $sample
     */
    protected function addClass(string $parentNamespace, string $key, array $sample): ClassType
    {
        $name = DtoNames::className($key);
        $namespace = ltrim("{$parentNamespace}\\{$name}", '\\');

        $properties = [];
        foreach ($sample as $propertyKey => $value)
            $properties[] = new DtoProperty((string) $propertyKey, $this->typeOf($namespace, (string) $propertyKey, $value), true, true);

        $this->classes[] = new DtoClass($namespace, $name, $properties);

        return new ClassType($namespace, $name);
    }

    protected function typeOf(string $namespace, string $key, mixed $value): DtoType
    {
        if (!is_array($value)) {
            return match (true) {
                null === $value => new MixedType(),
                is_bool($value) => new ScalarType(Scalar::Bool),
                is_int($value) => new ScalarType(Scalar::Int),
                is_float($value) => new ScalarType(Scalar::Float),
                default => new ScalarType(Scalar::String),
            };
        }

        if (!count($value))
            return new ListType(new MixedType());

        if (!Utils::isList($value))
            return $this->addClass($namespace, $key, $value);

        return new ListType($this->typeOf($namespace, $key, $value[0]));
    }
}
