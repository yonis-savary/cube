<?php

namespace Cube\Tests\Units\Web;

use Cube\Core\Exceptions\ResponseException;
use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Web\Classes\BlockingMiddleware;
use Cube\Tests\Units\Web\Classes\CountingApi;
use Cube\Tests\Units\Web\Classes\ProductReader;
use Cube\Tests\Units\Web\Classes\TracingMiddleware;
use Cube\Tests\Units\Web\Examples\PriceRequest;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use Cube\Utils\Path;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function Cube\measureTimeOf;

/**
 * @internal
 */
class RouterTest extends TestCase
{
    use HasTemporaryStorage;

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
     * Creates a BUNCH of routes (13 699 of them) and tests routing performances.
     */
    public function testPerformances()
    {
        $router = $this->newRouter();

        $keywords = ['zim', 'zam', 'zoom', 'boo', 'bar', 'foo', 'boom'];

        $count = 0;
        $this->createRoutesWithKeywords($router, $keywords, $count);

        // The first route() of a process pays for the Injector resolving a callback for the
        // first time, ~15ms of one-off work that has nothing to do with walking the route tree
        $router->route(new Request('GET', '/zim'));

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

        $router = $this->newRouter();

        $this->expectException(InvalidArgumentException::class);
        $router->addRoutes(Route::get('/', ['InexistentClass', 'getCountResponseMethod']));

        $this->expectException(InvalidArgumentException::class);
        $router->addRoutes(Route::get('/', [static::class, 'inexistent']));

        $router->addRoutes(Route::get('/', [self::class, 'getCountResponseMethod'], extras: ['count' => 1]));
        $response = $router->route(new Request('GET', '/'));
        $this->assertInstanceOf(Response::class, $response);

    }

    public function testAnUnknownPathAnswersNotFound()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::get('/products', fn () => Response::ok()));

        $this->assertEquals(StatusCode::NOT_FOUND, $router->route(new Request('GET', '/orders'))->getStatusCode());
    }

    public function testAKnownPathWithTheWrongMethodAnswersMethodNotAllowed()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::post('/products', fn () => Response::ok()));

        $response = $router->route(new Request('GET', '/products'));

        $this->assertEquals(StatusCode::METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertStringContainsString('POST', $response->getBody());
        $this->assertEquals('text/plain', $response->getHeader('content-type'));
    }

    public function testAnOptionsRequestAnswersTheAllowedMethods()
    {
        $router = $this->newRouter();
        $router->addRoutes(
            Route::post('/products', fn () => Response::ok()),
            new Route('/products', fn () => Response::ok(), ['PUT']),
        );

        $response = $router->route(new Request('OPTIONS', '/products'));

        $this->assertEquals(StatusCode::NO_CONTENT, $response->getStatusCode());

        $allowed = $response->getHeader('access-control-allow-methods');
        $this->assertStringContainsString('POST', $allowed);
        $this->assertStringContainsString('PUT', $allowed);
    }

    /**
     * A controller with nothing to return means an empty body, not the string "null" : the
     * call stack used to hand that return value to Response::json().
     */
    public function testAControllerReturningNothingAnswersNoContent()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::get('/ping', fn () => null));

        $response = $router->route(new Request('GET', '/ping'));

        $this->assertEquals(StatusCode::NO_CONTENT, $response->getStatusCode());
        $this->assertEquals('', $response->getBody());
    }

    public function testAControllerReturningAnArrayAnswersJson()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::get('/products', fn () => [['name' => 'screen']]));

        $response = $router->route(new Request('GET', '/products'));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals([['name' => 'screen']], $response->getJSON());
    }

    public function testMiddlewaresRunBeforeTheController()
    {
        TracingMiddleware::reset();

        $router = $this->newRouter();
        $router->addRoutes(
            Route::get('/products', fn () => Response::ok('reached'), [TracingMiddleware::class])
        );

        $response = $router->route(new Request('GET', '/products'));

        $this->assertEquals([TracingMiddleware::class], TracingMiddleware::$trace);
        $this->assertEquals('reached', $response->getBody());
    }

    /**
     * The call stack converts what comes back from a middleware too, and a Response is already
     * an answer : encoding it again gave "{}", since a Response holds no public property.
     */
    public function testAResponseCrossingAMiddlewareIsLeftUntouched()
    {
        TracingMiddleware::reset();

        $router = $this->newRouter();
        $router->addRoutes(
            Route::get('/products', fn () => Response::ok('reached'), [TracingMiddleware::class])
        );

        $response = $router->route(new Request('GET', '/products'));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals('reached', $response->getBody());
    }

    public function testAControllerReturnIsConvertedOnceBehindAMiddleware()
    {
        TracingMiddleware::reset();

        $router = $this->newRouter();
        $router->addRoutes(
            Route::get('/products', fn () => [['name' => 'screen']], [TracingMiddleware::class]),
            Route::get('/ping', fn () => null, [TracingMiddleware::class]),
        );

        $this->assertEquals([['name' => 'screen']], $router->route(new Request('GET', '/products'))->getJSON());

        $empty = $router->route(new Request('GET', '/ping'));
        $this->assertEquals(StatusCode::NO_CONTENT, $empty->getStatusCode());
        $this->assertEquals('', $empty->getBody());
    }

    public function testAMiddlewareCanAnswerWithoutTheController()
    {
        $reached = false;

        $router = $this->newRouter();
        $router->addRoutes(
            Route::get('/products', function () use (&$reached) {
                $reached = true;

                return Response::ok();
            }, [BlockingMiddleware::class])
        );

        $response = $router->route(new Request('GET', '/products'));

        $this->assertFalse($reached);
        $this->assertEquals(StatusCode::FORBIDDEN, $response->getStatusCode());
    }

    public function testAGroupLendsItsPrefixAndMiddlewaresToItsRoutes()
    {
        TracingMiddleware::reset();

        $router = $this->newRouter();
        $router->group('/api', [TracingMiddleware::class], function: function (Router $router) {
            $router->addRoutes(Route::get('/products', fn () => Response::ok('grouped')));
        });

        $this->assertEquals(StatusCode::NOT_FOUND, $router->route(new Request('GET', '/products'))->getStatusCode());

        $response = $router->route(new Request('GET', '/api/products'));

        $this->assertEquals('grouped', $response->getBody());
        $this->assertEquals([TracingMiddleware::class], TracingMiddleware::$trace);
    }

    public function testAResponseExceptionIsUnwrappedByTheRouter()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::get('/products', function () {
            throw new ResponseException('nope', Response::unprocessableContent('aborted'));
        }));

        $response = $router->route(new Request('GET', '/products'));

        $this->assertEquals(StatusCode::UNPROCESSABLE_CONTENT, $response->getStatusCode());
        $this->assertEquals('aborted', $response->getBody());
    }

    /**
     * The configured apis are already known by the constructor : registering them again in
     * loadRoutes() listed them twice, so every one of them was asked about every request twice.
     */
    public function testAConfiguredApiIsRegisteredOnce()
    {
        $api = new CountingApi();
        $router = new Router(new RouterConfiguration(false, false, false, [$api], [], '/'));

        $router->route(new Request('GET', '/counting-api'));

        $this->assertCount(1, $router->getApis());
        $this->assertEquals(1, $api->routesCalls);
        $this->assertEquals(1, $api->handleCalls);
    }

    public function testAnApiCanClaimARequestWithItsOwnResponse()
    {
        $api = new CountingApi(Response::ok('claimed'));
        $router = new Router(new RouterConfiguration(false, false, false, [$api], [], '/'));
        $router->addRoutes(Route::get('/products', fn () => Response::ok('from the route')));

        $this->assertEquals('claimed', $router->route(new Request('GET', '/products'))->getBody());
    }

    public function testAnApiRegistersItsOwnRoutes()
    {
        $api = new CountingApi();
        $router = new Router(new RouterConfiguration(false, false, false, [$api], [], '/'));

        $this->assertEquals('from routes()', $router->route(new Request('GET', '/counting-api'))->getBody());
    }

    public function testARouteIsReachableWithOrWithoutItsTrailingSlash()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::get('/products/', fn () => Response::ok('products')));

        $this->assertEquals('products', $router->route(new Request('GET', '/products'))->getBody());
    }

    /** The 422 body echoes the input as JSON but carried no Content-Type, so browsers rendered it as HTML. */
    public function testAnInvalidRequestAnswersJson()
    {
        $router = $this->newRouter();
        $router->addRoutes(Route::get('/prices', fn (PriceRequest $request) => Response::ok()));

        $response = $router->route(new Request('GET', '/prices', ['price' => '<svg onload=alert(1)>']));

        $this->assertEquals(StatusCode::UNPROCESSABLE_CONTENT, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('content-type'));
    }

    /** A cached router used to call the Route while caching it, and reused it without checking the method nor the slugs. */
    public function testACachedRouterAnswersLikeAnUncachedOne()
    {
        $this->setUpTemporaryStorage('router-cache-test-');

        try {
            Cache::withInstance(new Cache(new CacheConfiguration(new LocalDiskCache($this->storage))), function () {
                $router = new Router(new RouterConfiguration(true, false, false, [], [], '/'));
                $router->addRoutes(Route::get('/products/{int:id}', [ProductReader::class, 'read']));

                $this->assertEquals('product 5', $router->route(new Request('GET', '/products/5'))->getBody());
                $this->assertEquals('product 5', $router->route(new Request('GET', '/products/5'))->getBody());
                $this->assertEquals('product 7', $router->route(new Request('GET', '/products/7'))->getBody());
                $this->assertEquals(StatusCode::METHOD_NOT_ALLOWED, $router->route(new Request('DELETE', '/products/5'))->getStatusCode());
            });
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    public function testARequiredFileAddsItsRoutesToTheCurrentGroup()
    {
        $this->setUpTemporaryStorage('router-require-test-');

        try {
            $this->storage->write('socket.php', '<?php $router->addRoutes(\Cube\Web\Router\Route::get("/status", fn () => \Cube\Web\Http\Response::ok("up")));');
            $file = Path::toRelative($this->storage->path('socket.php'));

            $router = $this->newRouter();
            $router->group('/internal', function: fn (Router $router) => $router->require($file));

            $this->assertEquals(StatusCode::NOT_FOUND, $router->route(new Request('GET', '/status'))->getStatusCode());
            $this->assertEquals('up', $router->route(new Request('GET', '/internal/status'))->getBody());
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    public function testRequiringAMissingFileThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->newRouter()->require('App/routes/missing.php');
    }

    protected function newRouter(): Router
    {
        return new Router(new RouterConfiguration(false, false, false, [], [], '/'));
    }
}
