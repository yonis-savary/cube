<?php

namespace YonisSavary\Cube\Database\Query;

use InvalidArgumentException;
use YonisSavary\Cube\Models\DummyModel;
use YonisSavary\Cube\Models\ModelField;

class Field
{
    public function __construct(
        public readonly ?string $table=null,
        public readonly ?string $field=null,
        public readonly ?string $expression=null,
        public readonly ?string $alias=null,
        public readonly ?string $model=DummyModel::class,
        public readonly ?ModelField $modelField=null
    )
    {
        if ($expression === null && $table === null)
            throw new InvalidArgumentException("Either a table field or an expression is needed");
    }
}