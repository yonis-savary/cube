<?php

namespace Cube\Data\Models;

/**
 * @template TModel of Model
 * @extends ModelFactory<TModel>
 */
final class GenericModelFactory extends ModelFactory
{
    /**
     * @param class-string<TModel> $modelClass
     */
    public function __construct(
        private string $modelClass
    ) {}

    public static function getModelClass(): string
    {
        return Model::class;
    }

    protected function getFactoredClass(): string
    {
        return $this->modelClass;
    }
}
