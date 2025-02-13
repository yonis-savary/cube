<?php

namespace App\Controllers;

use App\Controllers\Requests\StoreProductRequest;
use YonisSavary\Cube\Http\Response;
use App\Models\Product;
use App\Models\ProductManager;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Web\Controller;
use YonisSavary\Cube\Web\Route;
use YonisSavary\Cube\Web\Router;

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