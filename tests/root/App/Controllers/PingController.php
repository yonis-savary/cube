<?php

namespace App\Controllers;

use Cube\Http\Request;
use Cube\Web\Controller;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class PingController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get("/ping", [self::class, "ping"])
        );
    }

    public static function ping(Request $request)
    {
        return "OK";
    }
}