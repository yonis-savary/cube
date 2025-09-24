<?php 

namespace App\Controllers;

use Cube\Core\Autoloader;
use Cube\Web\Controller;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

class PingController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get("/", [self::class, "rootAndDeleteAPCU"]),
            Route::get("/ping", [self::class, "ping"])
        );
    }

    public static function rootAndDeleteAPCU()
    {
        Autoloader::clearApcuCache();
        return Response::noContent();
    }

    public static function ping() {
        return Response::json([
            'message' => "OK",
            'loaded_with_apcu' => Autoloader::$loadedThroughApcu
        ]);
    }
}