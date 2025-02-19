<?php

namespace App\Controllers;

use App\Controllers\Requests\StoreProductRequest;
use Cube\Http\Response;
use App\Models\Product;
use Cube\Http\Request;
use Cube\Web\Controller;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post("/product", [self::class, "storeProduct"]),
            Route::get("/product/{int:id}", [self::class, "getProduct"]),
        );
    }

    public static function storeProduct(StoreProductRequest $request)
    {
        $product = Product::fromRequest($request);

        $product->save();
        $product->managers()->load();

        return Response::json($product);
    }

    public static function getProduct(Request $request, int $id)
    {
        $product = Product::find($id);
        $product->managers()->load();

        return $product;
    }
}