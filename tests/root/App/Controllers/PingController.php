<?php

namespace App\Controllers;

use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Web\Controller;
use YonisSavary\Cube\Web\Route;
use YonisSavary\Cube\Web\Router;

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