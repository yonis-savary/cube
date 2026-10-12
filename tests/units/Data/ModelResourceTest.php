<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Models\ModuleUser;
use Cube\Tests\Units\Models\Product;
use Cube\Tests\Units\Models\User;
use Cube\Tests\Units\Web\Classes\ProductNameResource;
use Cube\Tests\Units\Web\Classes\UserResource;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ModelResourceTest extends TestCase
{
    protected function setUp(): void
    {
        Database::setInstance((new SQLiteProvider())->getEmptyDatabase());
    }

    protected function tearDown(): void
    {
        Database::removeInstance();
    }

    public function testAModelAnsweredAsIsIsAnsweredWhole()
    {
        $product = Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);

        $this->assertEquals($product->toArray(), Response::json($product)->getJSON());
    }

    public function testAResourceShapesWhatARouteAnswers()
    {
        $alice = User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);

        $router = new Router(new RouterConfiguration(false, false, false));
        $router->addRoutes(
            Route::get('/user', fn () => UserResource::of(User::find($alice->id()))),
            Route::get('/users', fn () => ['everyone' => UserResource::of(User::select()->fetchBunch())]),
        );

        $this->assertEquals(['id' => $alice->id(), 'login' => 'alice'], $router->route(new Request('GET', '/user'))->getJSON());
        $this->assertArrayNotHasKey('password', $router->route(new Request('GET', '/users'))->getJSON()['everyone'][0]);
    }

    public function testALoadedRelationIsAnsweredWhole()
    {
        $alice = User::insertArray(['login' => 'alice', 'password' => 'hash-of-alice', 'type' => 1]);
        $membership = ModuleUser::insertArray(['user' => $alice->id(), 'module' => 1])->load('_user');

        $this->assertEquals('alice', Response::json($membership)->getJSON()['_user']['login']);
    }

    public function testAResourceAcceptsOneModelOrSeveral()
    {
        $screen = Product::insertArray(['name' => 'Screen', 'price_dollar' => 10]);
        $mouse = Product::insertArray(['name' => 'Mouse']);

        $this->assertEquals(['name' => 'Screen'], ProductNameResource::of($screen));
        $this->assertEquals([['name' => 'Screen'], ['name' => 'Mouse']], ProductNameResource::of([$screen, $mouse]));
    }
}
