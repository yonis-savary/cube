<?php

namespace Cube\Data\Models;

use Cube\Core\Injector;
use Cube\Data\Bunch;

/**
 * @template TModel of Model
 * @template TModelArray of array<string,mixed>
 */
abstract class ModelResource
{
    /**
     * @return class-string<TModel>
     */
    abstract public static function getModelClass(): string;

    /**
     * @param TModel $model
     * @return TModelArray A model left in it is answered whole : shape it with another resource's `of()`
     */
    abstract public function toArray(Model $model): array;

    /**
     * @param TModel|iterable<TModel> $models
     * @return TModelArray|TModelArray[]
     */
    final public static function of(Model|array|Bunch $models): array
    {
        $resource = Injector::getInstance()->instanciate(static::class);

        if ($models instanceof Model)
            return $resource->toArray($models);

        return Bunch::of($models)
            ->map(fn (Model $model) => $resource->toArray($model))
            ->toArray();
    }
}
