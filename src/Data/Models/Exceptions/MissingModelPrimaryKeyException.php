<?php

namespace Cube\Data\Models\Exceptions;

use Cube\Data\Models\Model;

class MissingModelPrimaryKeyException extends \RuntimeException
{
    /**
     * @param class-string<Model> $model
     */
    public function __construct(
        public readonly string $model,
        public readonly string $method
    ) {
        parent::__construct("{$model} does not have a primary key, {$method}() needs one");
    }
}
