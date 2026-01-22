<?php

namespace TestApplication\Controllers;

use Cube\Web\Http\Request;
use Cube\Web\Controller;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class MathController extends Controller
{
    public function routes(Router $router): void
    {
        $router->group("maths", routes:[
            Route::get("/double/{n}", [self::class, 'doubleNumber'])
        ]);
    }

    public static function doubleNumber(Request $request, int $input)
    {
        return $input * 2;
    }
}
