<?php

namespace Cube\Web;

use Cube\Http\Request;
use Cube\Web\Router\Router;

abstract class WebAPI
{
    public function __construct(){}

    public function routes(Router $router): void
    {

    }

    /**
     * A service can directly handle a Request object when a router is looking for a matching route
     * This method can return either a Response that will be displayed or a Route that will be executed
     */
    public function handle(Request $request): mixed
    {
        return $request;
    }
}