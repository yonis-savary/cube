<?php

namespace App\Controllers\Models;

use App\Models\Product;
use Cube\Web\ModelAPI\ModelAPI;
use Cube\Web\Router\RouteGroup;

class ProductController extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }

    public function getRouteGroup(): RouteGroup
    {
        return new RouteGroup('auto-api');
    }
}
