<?php

namespace Cube\Web\Http;

use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;

class HttpMockServer
{
    protected Router $router;

    public static function fromRoutes(Route ...$routes): self {
        $server = new static();
        $server->addRoutes(...$routes);

        return $server;
    }

    /**
     * @param array<string,\Closure|Response> $routes
     */
    public static function fromArray(array $routes): self {
        $server = new static();

        foreach ($routes as $path => $handler) {
            if ($handler instanceof Response)
                $handler = fn() => $handler;

            $server->addRoutes(new Route($path, $handler));
        }
        return $server;
    }

    protected function getRouterConfiguration(): RouterConfiguration
    {
        return new RouterConfiguration(false, false, false, [], [], '/');
    }

    public function __construct()
    {
        $this->router = new Router($this->getRouterConfiguration());
        $this->routes($this->router);
    }

    public function routes(Router $router) {
    }

    public function addRoutes(Route ...$routes) {
        $this->router->addRoutes(...$routes);
    }

    protected function removeHostFromUrl(string $url): string 
    {
        $url = preg_replace('~^https?://~', '', $url);
        $url = preg_replace('~^.+?/~', '', $url);
        return $url;
    }

    public function handle(Request $request): Response {
        $url = $request->getPath();
        if (str_starts_with($url, 'http'))
            $url = $this->removeHostFromUrl($url);

        $request->setPath($url);

        return $this->router->route($request);
    }
}