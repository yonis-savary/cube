<?php

namespace Cube\Tests\Units\Web;

use Cube\Http\Request;
use Cube\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\TestCase;

use function Cube\measureTimeOf;

/**
 * @internal
 *
 * @coversNothing
 */
class RouterTest extends TestCase
{
    public static function getCountResponse(Request $request)
    {
        return $request->getRoute()->getExtras()['count'];
    }

    /**
     * Creates a BUNCH of routes (~100k) and test routing performances.
     */
    public function testPerformances()
    {
        $this->assertTrue(true);
        $router = new Router(new RouterConfiguration(false, false, false, [], [], '/'));

        $keywords = ['zim', 'zam', 'zoom', 'boo', 'bar', 'foo', 'boom', 'tim'];

        $count = 0;
        $this->createRoutesWithKeywords($router, $keywords, $count);

        $assertRoutingTakeLessThan = function (string $request, int $routingTimeMs, string $expectedResponse) use (&$router) {
            $routingTimeMicro = $routingTimeMs * 1000;

            $response = null;

            $time = measureTimeOf(function () use (&$response, &$router, $request) {
                $response = $router->route(new Request('GET', $request));
            });

            // @var Response $response

            $this->assertLessThan($routingTimeMicro, $time);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals($expectedResponse, $response->getBody());
        };

        $assertRoutingTakeLessThan('/tim/boom/foo/bar/boo/zoom/zam/zim', 5, '109600');
        $assertRoutingTakeLessThan('/boo/bar/foo/boom/tim/zim/zam', 5, '48199');
        $assertRoutingTakeLessThan('/zim', 2, '1');
    }

    protected function createRoutesWithKeywords(Router $router, array $keywords, int &$count = 0)
    {
        foreach ($keywords as $keyword) {
            ++$count;
            $router->group($keyword, callback: function (Router $router, RouteGroup $group) use (&$keywords, &$keyword, &$count) {
                $router->addRoutes(
                    Route::get('/', [self::class, 'getCountResponse'], extras: ['count' => $count])
                );
                $this->createRoutesWithKeywords($router, array_diff($keywords, [$keyword]), $count);
            });
        }
    }
}
