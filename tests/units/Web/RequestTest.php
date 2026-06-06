<?php 

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Web\Examples\PriceRequest;
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

    public function testOnlyReturnsSpecifiedKeys()
    {
        $request = new Request('POST', '/', [], ['name' => 'screen', 'price_dollar' => 10, 'extra' => 'ignored']);

        $result = $request->only(['name', 'price_dollar']);

        $this->assertEquals(['name' => 'screen', 'price_dollar' => 10], $result);
        $this->assertArrayNotHasKey('extra', $result);
    }

    public function testOnlySilentlyIgnoresMissingKeys()
    {
        $request = new Request('POST', '/', [], ['name' => 'screen']);

        $result = $request->only(['name', 'price_dollar']);

        $this->assertEquals(['name' => 'screen'], $result);
        $this->assertArrayNotHasKey('price_dollar', $result);
    }

    public function testOnlyWithValidated()
    {
        $baseRequest = new Request('GET', '/', ['price' => '50'], []);
        $request = PriceRequest::fromRequest($baseRequest); // validated() will parse the price to int

        $unvalidatedOnly = $request->only(['price'], false);
        $validatedOnly = $request->only(['price'], true);

        $this->assertTrue($unvalidatedOnly['price'] === '50');
        $this->assertTrue($validatedOnly['price'] === 50);
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