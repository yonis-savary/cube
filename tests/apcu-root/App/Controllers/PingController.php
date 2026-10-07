<?php 

namespace App\Controllers;

use Cube\Core\Autoloader;
use Cube\Event\Events\ApplicationsLoaded;
use Cube\Event\Events\FrameworkLoaded;
use Cube\Event\Events\PreRouting;
use Cube\Web\Controller;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Psr\Log\LoggerInterface;

class PingController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get("/", [self::class, "rootAndDeleteAPCU"]),
            Route::get("/ping", [self::class, "ping"]),
            Route::get("/provided-logger", [self::class, "providedLogger"])
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
            'loaded_with_apcu' => Autoloader::$loadedThroughApcu,
            'framework_loaded' => $GLOBALS['heard'][FrameworkLoaded::class] ?? false,
            'applications_loaded' => $GLOBALS['heard'][ApplicationsLoaded::class] ?? null,
            'pre_routing' => $GLOBALS['heard'][PreRouting::class] ?? false,
        ]);
    }

    public static function providedLogger(Request $request, LoggerInterface $logger)
    {
        return Response::json(['logger' => $logger::class]);
    }
}