<?php

namespace App\Controllers\Models;

use App\Models\Product;
use Cube\Web\ModelAPI\ModelAPI;

/**
 * @extends ModelAPI<Product>
 */
class ProductModelAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }
}
