<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Tests\Units\Models\Product;
use Cube\Web\ModelAPI\ModelAPI;

/**
 * A full CRUD over the Product table.
 *
 * ModelAPI extends Controller, so it would be discovered and registered by any router
 * loading controllers : every test here builds its own router with that turned off.
 */
class ProductAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }
}
