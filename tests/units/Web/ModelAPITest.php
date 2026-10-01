<?php

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Env\Configuration;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Web\Classes\BlockingMiddleware;
use Cube\Tests\Units\Web\Classes\ProductAPI;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\ModelAPI\ModelAPI;
use Cube\Web\ModelAPI\ModelAPIConfiguration;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ModelAPI only ever talks to the model layer, one driver is enough to pin its routing
 * and its status codes : the multi-driver ground is covered by ModelTest.
 *
 * @internal
 */
class ModelAPITest extends TestCase
{
    use TestMultipleDrivers;

    protected Database $database;

    protected function setUp(): void
    {
        $this->database = (new SQLiteProvider())->getEmptyDatabase();
        Database::setInstance($this->database);
    }

    protected function tearDown(): void
    {
        Database::removeInstance();
    }

    public function testTheTableNameBecomesTheRoutePrefix()
    {
        $paths = array_map(fn ($route) => $route->getPath(), $this->newRouter()->getRoutes());

        $this->assertEquals(['/product', '/product', '/product', '/product'], $paths);
    }

    public function testOnlyTheDeclaredModesAreRegistered()
    {
        $readOnly = new class() extends ProductAPI {
            public function getModes(): array
            {
                return [ModelAPI::READ];
            }
        };

        $router = new Router(new RouterConfiguration(false, false, false, [$readOnly], [], '/'));
        $router->loadRoutes();

        $this->assertCount(1, $router->getRoutes());
        $this->assertEquals(['GET'], $router->getRoutes()[0]->getMethods());
    }

    public function testReadingAnswersEveryRow()
    {
        Product::insertArray(['name' => 'Screen']);
        Product::insertArray(['name' => 'Mouse']);

        $response = $this->route(new Request('GET', '/product'));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertCount(2, $response->getJSON());
    }

    public function testReadingSearchesInsideAStringColumn()
    {
        Product::insertArray(['name' => 'Office Screen']);
        Product::insertArray(['name' => 'Mouse']);

        $response = $this->route(new Request('GET', '/product', ['name' => 'Screen']));

        $rows = $response->getJSON();
        $this->assertCount(1, $rows);
        $this->assertEquals('Office Screen', $rows[0]['name']);
    }

    /**
     * The search used to quote its column with backticks, which Postgres refuses
     */
    #[DataProvider('getDatabases')]
    public function testReadingSearchesOnEveryDriver(Database $database)
    {
        $database->asGlobalInstance(function () {
            Product::insertArray(['name' => 'Office Screen']);
            Product::insertArray(['name' => 'Mouse']);

            $rows = $this->route(new Request('GET', '/product', ['name' => 'Screen']))->getJSON();

            $this->assertEquals(['Office Screen'], array_column($rows ?? [], 'name'));
        });
    }

    public function testCreatingAnswersCreated()
    {
        $response = $this->route(new Request('POST', '/product', [], ['name' => 'Keyboard']));

        $this->assertEquals(StatusCode::CREATED, $response->getStatusCode());
        $this->assertEquals('Keyboard', Product::findWhere(['name' => 'Keyboard'])->name);
    }

    public function testCreatingSeveralRowsFromAJsonList()
    {
        $request = new Request(
            'POST',
            '/product',
            [],
            [],
            ['content-type' => 'application/json'],
            [],
            json_encode([['name' => 'Desk'], ['name' => 'Chair']])
        );

        $response = $this->route($request);

        $this->assertEquals(StatusCode::CREATED, $response->getStatusCode());
        $this->assertCount(2, $response->getJSON());
        $this->assertCount(2, Product::select()->fetch());
    }

    public function testUpdatingAnswersTheNewRow()
    {
        $product = Product::insertArray(['name' => 'Screen']);

        $response = $this->route(new Request('PUT', '/product', [], ['id' => $product->id(), 'name' => 'Bigger screen']));

        $this->assertEquals('Bigger screen', $response->getJSON()['name']);
        $this->assertEquals('Bigger screen', Product::find($product->id())->name);
    }

    public function testUpdatingWithoutAPrimaryKeyIsRefused()
    {
        $response = $this->route(new Request('PUT', '/product', [], ['name' => 'Nameless']));

        $this->assertEquals(StatusCode::UNPROCESSABLE_CONTENT, $response->getStatusCode());
    }

    public function testUpdatingAnUnknownRowIsRefused()
    {
        $response = $this->route(new Request('PUT', '/product', [], ['id' => 404, 'name' => 'Ghost']));

        $this->assertEquals(StatusCode::UNPROCESSABLE_CONTENT, $response->getStatusCode());
    }

    public function testDeleting()
    {
        $product = Product::insertArray(['name' => 'Screen']);

        $response = $this->route(new Request('DELETE', '/product', [], ['id' => $product->id()]));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertNull(Product::find($product->id()));
    }

    public function testDeletingAnUnknownRowIsRefused()
    {
        $response = $this->route(new Request('DELETE', '/product', [], ['id' => 404]));

        $this->assertEquals(StatusCode::UNPROCESSABLE_CONTENT, $response->getStatusCode());
    }

    /** ModelAPIConfiguration is never read, so the middlewares it declares never guard the API. */
    public function testConfiguredMiddlewaresGuardTheApi()
    {
        Configuration::withInstance(new Configuration(new ModelAPIConfiguration([BlockingMiddleware::class])), function () {
            $response = $this->route(new Request('GET', '/product'));

            $this->assertEquals(StatusCode::FORBIDDEN, $response->getStatusCode());
        });
    }

    protected function route(Request $request): Response
    {
        return $this->newRouter()->route($request);
    }

    protected function newRouter(): Router
    {
        $router = new Router(new RouterConfiguration(false, false, false, [new ProductAPI()], [], '/'));
        $router->loadRoutes();

        return $router;
    }
}
