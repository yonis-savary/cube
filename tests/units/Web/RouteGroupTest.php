<?php

namespace Cube\Tests\Units\Web;

use Cube\Tests\Units\Web\Classes\TracingMiddleware;
use Cube\Web\Http\Request;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class RouteGroupTest extends TestCase
{
    public function testAGroupLendsItsPrefixToTheRoutesItHolds()
    {
        $group = new RouteGroup('/api');
        $route = Route::get('/products', fn () => null);

        $group->addRoutes($route);

        $this->assertEquals('/api/products', $route->getPath());
    }

    public function testAGroupAddsItsMiddlewaresAndExtrasToWhatTheRouteAlreadyHas()
    {
        $group = new RouteGroup('/api', [TracingMiddleware::class], ['scope' => 'api']);
        $route = Route::get('/products', fn () => null, [], ['label' => 'products']);

        $group->addRoutes($route);

        $this->assertEquals([TracingMiddleware::class], $route->getMiddlewares());
        $this->assertEquals(['label' => 'products', 'scope' => 'api'], $route->getExtras());
    }

    public function testMergingTwoGroupsJoinsWhatTheyDeclare()
    {
        $merged = (new RouteGroup('/api', ['First'], ['a' => 1]))
            ->mergeWith(new RouteGroup('/v2', ['Second'], ['b' => 2]));

        $this->assertEquals('/api/v2', $merged->prefix);
        $this->assertEquals(['First', 'Second'], $merged->middlewares);
        $this->assertEquals(['a' => 1, 'b' => 2], $merged->extras);
    }

    public function testGetRoutesWalksTheWholeTree()
    {
        $root = new RouteGroup('/api');
        $root->addRoutes(Route::get('/health', fn () => null));

        $subGroup = $root->addSubGroup(new RouteGroup('/v2'));
        $subGroup->addRoutes(Route::get('/products', fn () => null));

        $paths = array_map(fn (Route $route) => $route->getPath(), $root->getRoutes());

        $this->assertEquals(['/api/health', '/api/v2/products'], $paths);
    }

    public function testASubGroupIsKeptByItsParent()
    {
        $root = new RouteGroup('/api');
        $root->addSubGroup(new RouteGroup('/v2'));

        $this->assertCount(1, $root->getElements());
        $this->assertInstanceOf(RouteGroup::class, $root->getElements()[0]);
    }

    public function testAGroupOnlyLooksInsideItselfForAMatchingPath()
    {
        $root = new RouteGroup('/');
        $apiGroup = $root->addSubGroup(new RouteGroup('/api'));
        $apiGroup->addRoutes(Route::get('/products', fn () => null));

        $this->assertInstanceOf(Route::class, $root->findMatchingRoute(new Request('GET', '/api/products')));
        $this->assertFalse($root->findMatchingRoute(new Request('GET', '/products')));
    }

    public function testAWrongMethodIsCollectedInsteadOfEndingTheWalk()
    {
        $root = new RouteGroup('/');
        $root->addRoutes(Route::post('/products', fn () => null));

        $exceptions = [];
        $this->assertFalse($root->findMatchingRoute(new Request('GET', '/products'), $exceptions));
        $this->assertCount(1, $exceptions);
        $this->assertEquals(['POST'], $exceptions[0]->allowedMethods);
    }

    public function testARouteWithAnUnreachableCallbackIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);

        (new RouteGroup('/'))->addRoutes(Route::get('/products', ['App\\NoSuchController', 'index']));
    }
}
