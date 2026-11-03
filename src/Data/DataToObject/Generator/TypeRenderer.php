<?php

namespace Cube\Data\DataToObject\Generator;

use Cube\Data\Bunch;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ListType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\MapType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\MixedType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ScalarType;

class TypeRenderer
{
    /**
     * @param bool $objectsAtAnyDepth classes are objects wherever they sit (what you build and send), rather
     *                                than only where `DataToObject::fromData()` builds them (what you receive)
     */
    public function __construct(
        protected GenerationTarget $target,
        protected PhpReferences $references,
        protected bool $objectsAtAnyDepth = false,
    ) {}

    /**
     * @return ClassType[] the classes `$type` names, at any depth when `$deep`, else only where `DataToObject::fromData()` builds them
     */
    public static function classesOf(DtoType $type, bool $deep): array
    {
        return match (true) {
            $type instanceof ClassType => [$type],
            $type instanceof ListType && ($deep || $type->item instanceof ClassType) => self::classesOf($type->item, $deep),
            $type instanceof MapType && $deep => self::classesOf($type->value, $deep),
            default => [],
        };
    }

    public function nameOf(ClassType $type): string
    {
        return $this->referenceOf($this->target->fullNameOf($type));
    }

    public function referenceOf(string $fullName): string
    {
        return $this->references->nameOf($fullName);
    }

    public function nativeTypeOf(DtoType $type): string
    {
        return match (true) {
            $type instanceof ClassType => $this->nameOf($type),
            $type instanceof ScalarType => $type->scalar->value,
            $type instanceof MixedType => 'mixed',
            default => 'array',
        };
    }

    public function needsDocType(DtoType $type): bool
    {
        return $type instanceof ListType
            || $type instanceof MapType
            || ($type instanceof ScalarType && count($type->enum));
    }

    public function docTypeOf(DtoType $type, bool $isObject = true): string
    {
        $deep = $this->objectsAtAnyDepth;

        return match (true) {
            $type instanceof ClassType => $isObject ? $this->nameOf($type) : 'array<string,mixed>',
            $type instanceof ScalarType => count($type->enum)
                ? Bunch::wrap($type->enum)->map(fn ($value) => var_export($value, true))->join('|')
                : $type->scalar->value,
            $type instanceof ListType => 'list<'.$this->docTypeOf($type->item, $isObject && ($deep || !$type->item instanceof ListType)).'>',
            $type instanceof MapType => 'array<string,'.$this->docTypeOf($type->value, $isObject && $deep).'>',
            default => 'mixed',
        };
    }
}
