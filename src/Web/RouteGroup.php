<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Utils\Path;

class RouteGroup
{
    /** @var Route[] */
    protected array $routes = [];

    public function __construct(
        public string $prefix="/",
        public array $middlewares=[],
        public array $extras=[]
    ){}

    public function mergeWith(RouteGroup $group): RouteGroup
    {
        return new RouteGroup(
            Path::join($this->prefix, $group->prefix),
            array_merge($this->middlewares, $group->middlewares),
            array_merge($this->extras, $group->extras),
        );
    }

    public function applyToRoute(Route $route)
    {
        if ($prefix = $this->prefix)
            $route->setPath(Path::join($prefix, $route->getPath()));

        if ($middlewares = $this->middlewares)
            $route->setMiddlewares(array_merge($route->getMiddlewares(), $middlewares));

        if ($extras = $this->extras)
            $route->setExtras(array_merge($route->getExtras(), $extras));
    }

    public function matches(Request $request): bool
    {
        $route = new Route("/{any:any}", fn() => null);
        $this->applyToRoute($route);

        return ($request->getPath() === $this->prefix) || $route->match($request);
    }

    public function addRoute(Route $route): void
    {
        $this->applyToRoute($route);
        $this->routes[] = $route;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}