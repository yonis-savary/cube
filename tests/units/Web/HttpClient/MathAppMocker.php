<?php

namespace Cube\Tests\Units\Web\HttpClient;

use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class MathAppMocker extends HttpMockServer
{
    public function routes(Router $router)
    {
        $router->addRoutes(
            Route::get("/double/{int:number}", [self::class, 'double'])
        );
    }

    public static function double($req, int $number) {
        return Response::json(['result' => $number*2]);
    }
}