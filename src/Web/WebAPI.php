<?php

namespace Cube\Web;

use Cube\Http\Request;
use Cube\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;

abstract class WebAPI
{
    public function routes(Router $router): void {}

    /**
     * A service can directly handle a Request object when a router is looking for a matching route
     * This method can return
     * - a Response that will be displayed
     * - a Route that will be executed.
     * - `null` meaning that the API is not concerned
     */
    public function handle(Request $request): Response|Route|null { return null; }
}
