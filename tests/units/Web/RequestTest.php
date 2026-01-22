<?php 

namespace Cube\Tests\Units\Web;

use Cube\Core\Autoloader;
use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Models\Product;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    use TestMultipleDrivers;

    public static function fakeCallback(Request $request, Module $module) {
        /** @var Module $module */
        $module = $request->getSlugObject("module");

        return Response::json($module->label);
    }

    #[ DataProvider('getDatabases') ]
    public function test_parameters_binding(Database $database) {
        Database::withInstance($database, function(){
            $router = new Router(new RouterConfiguration());
            $router->addRoutes(
                Route::get("/{module}", [self::class, "fakeCallback"])
            );

            $response = $router->route(
                new Request("GET", "/1")
            );

            $this->assertEquals("product", $response->getJSON());
        });
    }
}