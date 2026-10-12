<?php

namespace Cube\Tests\Units\Web;

use Cube\Tests\Units\Web\Classes\RoleMiddleware;
use Cube\Web\Helpers\WebAPI;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AuthenticationMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        RoleMiddleware::$roles = [];
    }

    public function testAUserHoldingEveryPermissionReachesTheController()
    {
        RoleMiddleware::$roles = ['admin', 'writer'];

        $response = $this->routeGuardedBy(['admin']);

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals('reached', $response->getBody());
    }

    public function testAMissingPermissionIsReported()
    {
        RoleMiddleware::$roles = ['writer'];

        $response = $this->routeGuardedBy(['admin', 'writer']);

        $this->assertEquals(StatusCode::FORBIDDEN, $response->getStatusCode());
        $this->assertEquals('admin', $response->getBody());
    }

    /** As a common middleware, it read the extras of a route a WebAPI answer does not have, and crashed with a 500. */
    public function testAnApiAnswerHasNoRouteToGuard()
    {
        $api = new class extends WebAPI {
            public function routes(Router $router): void {}

            public function handle(Request $request): Response|Route|null
            {
                return Response::ok('from the api');
            }
        };
        $router = new Router(new RouterConfiguration(false, false, false, [$api], [RoleMiddleware::class]));

        $this->assertEquals('from the api', $router->route(new Request('GET', '/anything'))->getBody());
    }

    public function testUserHasPermissionAnswersWhatIsMissing()
    {
        RoleMiddleware::$roles = ['writer'];

        $this->assertTrue(RoleMiddleware::userHasPermission(['writer']));
        $this->assertEquals(['admin'], array_values(RoleMiddleware::userHasPermission(['admin', 'writer'])));
    }

    /**
     * A route can carry the middleware without having gone through guard(), and then holds no
     * permission list : reading that missing extra used to warn and hand null to array_diff().
     */
    public function testARouteWithoutAPermissionListRequiresNothing()
    {
        $router = $this->newRouter();
        $router->addRoutes(
            Route::get('/products', fn () => Response::ok('reached'), [RoleMiddleware::class])
        );

        $response = $router->route(new Request('GET', '/products'));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals('reached', $response->getBody());
    }

    /** A nested guard() replaced the outer permission list instead of adding to it. */
    public function testANestedGuardKeepsTheOuterPermissions()
    {
        RoleMiddleware::$roles = ['reader'];

        $router = $this->newRouter();
        RoleMiddleware::guard(['admin'], function (Router $router) {
            RoleMiddleware::guard(['reader'], function (Router $router) {
                $router->addRoutes(Route::get('/reports', fn () => Response::ok('reached')));
            }, $router);
        }, $router);

        $response = $router->route(new Request('GET', '/reports'));

        $this->assertEquals(StatusCode::FORBIDDEN, $response->getStatusCode());
    }

    public function testNestedGuardsLetThroughAUserHoldingEveryPermission()
    {
        RoleMiddleware::$roles = ['admin', 'reader'];

        $router = $this->newRouter();
        RoleMiddleware::guard(['admin'], function (Router $router) {
            RoleMiddleware::guard(['reader'], function (Router $router) {
                $router->addRoutes(Route::get('/reports', fn () => Response::ok('reached')));
            }, $router);
        }, $router);

        $this->assertEquals(StatusCode::OK, $router->route(new Request('GET', '/reports'))->getStatusCode());
    }

    protected function routeGuardedBy(array $permissions): Response
    {
        $router = $this->newRouter();

        RoleMiddleware::guard($permissions, function (Router $router) {
            $router->addRoutes(Route::get('/products', fn () => Response::ok('reached')));
        }, $router);

        return $router->route(new Request('GET', '/products'));
    }

    protected function newRouter(): Router
    {
        return new Router(new RouterConfiguration(false, false, false, [], [], '/'));
    }
}
