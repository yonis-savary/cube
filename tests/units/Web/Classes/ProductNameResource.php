<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Tests\Units\Models\Product;

/**
 * @extends ModelResource<Product>
 */
class ProductNameResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return Product::class;
    }

    public function toArray(Model $model): array
    {
        return ['name' => $model->name];
    }
}
