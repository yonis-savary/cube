<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Tests\Units\Models\Product;
use Cube\Web\ModelAPI\ModelAPI;

/**
 * @extends ModelAPI<Product>
 */
class PricelessProductAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }

    protected function getHiddenFields(): array
    {
        return ['price_dollar'];
    }
}
