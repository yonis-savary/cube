<?php

namespace Cube\Data\Database\Query;

class FieldComparaison
{
    public function __construct(
        public readonly string $source,
        public readonly string $sourceField,
        public readonly string $operator,
        public readonly string $target,
        public readonly string $targetField,
    ) {
        Identifier::assertName($source);
        Identifier::assertColumn($sourceField);
        Identifier::assertOperator($operator);
        Identifier::assertName($target);
        Identifier::assertColumn($targetField);
    }
}
