<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Web\Helpers\WebAPI;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

/**
 * Counts how many times the router asked it to register itself and to look at a request,
 * so a double registration cannot go unnoticed.
 */
class CountingApi extends WebAPI
{
    public int $routesCalls = 0;
    public int $handleCalls = 0;

    public function __construct(
        protected ?Response $answer = null
    ) {}

    public function routes(Router $router): void
    {
        ++$this->routesCalls;

        $router->addRoutes(
            Route::get('/counting-api', fn () => Response::ok('from routes()'))
        );
    }

    public function handle(Request $request): Response|Route|null
    {
        ++$this->handleCalls;

        return $this->answer;
    }
}
