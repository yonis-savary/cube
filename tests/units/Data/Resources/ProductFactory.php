<?php

namespace Cube\Tests\Units\Data\Resources;

use Cube\Data\Models\ModelFactory;
use Cube\Tests\Units\Models\Product;
use Faker\Generator;

/**
 * @extends ModelFactory<Product>
 */
class ProductFactory extends ModelFactory
{
    public static function getModelClass(): string
    {
        return Product::class;
    }

    protected function definition(Generator $faker): array
    {
        return ['price_dollar' => '9.99000'];
    }
}
