<?php

namespace Cube\Tests\Units\Web;

use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Cube\measureTimeOf;

/**
 * @internal
 */
class RouterTest extends TestCase
{
    public static function getCountResponse(Request $request)
    {
        return $request->getRoute()->getExtras()['count'];
    }

    public function getCountResponseMethod(Request $request) 
    {
        return $request->getRoute()->getExtras()['count'];
    }

    protected function createRoutesWithKeywords(Router $router, array $keywords, int &$count = 0)
    {
        foreach ($keywords as $keyword) {
            ++$count;
            $router->group($keyword, function: function (Router $router, RouteGroup $group) use (&$keywords, &$keyword, &$count) {
                $router->addRoutes(
                    Route::get('/', [self::class, 'getCountResponse'], extras: ['count' => $count])
                );
                $this->createRoutesWithKeywords($router, array_diff($keywords, [$keyword]), $count);
            });
        }
    }

    /**
     * Creates a BUNCH of routes (~100k) and test routing performances.
     */
    public function testPerformances()
    {
        $router = new Router(new RouterConfiguration(false, false, false, [], [], '/'));

        $keywords = ['zim', 'zam', 'zoom', 'boo', 'bar', 'foo', 'boom'];

        $count = 0;
        $this->createRoutesWithKeywords($router, $keywords, $count);

        $assertRoutingTakeLessThan = function (string $request, int $routingTimeMs, string $expectedResponse) use (&$router) {
            $routingTimeMicro = $routingTimeMs * 1000;

            $response = null;

            $time = measureTimeOf(function () use (&$response, &$router, $request) {
                $response = $router->route(new Request('GET', $request));
            });
            /** @var \Cube\Web\Http\Response $response */

            $this->assertLessThan($routingTimeMicro, $time);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals($expectedResponse, $response->getBody());
        };

        $assertRoutingTakeLessThan('/boom/foo/bar/boo/zoom/zam/zim', 5, '13699');
        $assertRoutingTakeLessThan('/boo/bar/foo/boom/zim/zam', 5, '7098');
        $assertRoutingTakeLessThan('/zim', 2, '1');
    }


    public function testMethodSupport() {

        $router = new Router(new RouterConfiguration(false, false, false, [], [], '/'));

        $this->expectException(InvalidArgumentException::class);
        $router->addRoutes(Route::get('/', ['InexistentClass', 'getCountResponseMethod']));

        $this->expectException(InvalidArgumentException::class);
        $router->addRoutes(Route::get('/', [static::class, 'inexistent']));

        $router->addRoutes(Route::get('/', [self::class, 'getCountResponseMethod'], extras: ['count' => 1]));
        $response = $router->route(new Request('GET', '/'));
        $this->assertInstanceOf(Response::class, $response);

    }
}
