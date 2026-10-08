<?php

namespace App\Controllers\Models;

use App\Models\Product;
use Cube\Web\Controller;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class ProductController extends Controller
{
    public function __construct(
        protected ProductModelAPI $products
    ) {}

    public function routes(Router $router): void
    {
        $router->group('/auto-api/product', routes: [
            Route::post('/', [self::class, 'create']),
            Route::get('/', [self::class, 'read']),
            Route::put('/{product}', [self::class, 'update']),
            Route::delete('/{product}', [self::class, 'delete']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->products->createItems($request);
    }

    /**
     * @return Product[]
     */
    public function read(Request $request): array
    {
        return $this->products->readItems($request);
    }

    public function update(Request $request, Product $product): Product
    {
        return $this->products->updateItem($product, $request);
    }

    public function delete(Request $request, Product $product): Response
    {
        return $this->products->deleteItem($product);
    }
}
