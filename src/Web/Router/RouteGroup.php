<?php

namespace Cube\Web\Router;

use Cube\Http\Exceptions\InvalidRequestMethodException;
use Cube\Http\Request;
use Cube\Utils\Path;

class RouteGroup
{
    /** @var Route[] */
    protected array $routes = [];

    /** @var RouteGroup[] */
    protected array $groups = [];

    public function __construct(
        public string $prefix = '/',
        public array $middlewares = [],
        public array $extras = []
    ) {}

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
        if ($prefix = $this->prefix) {
            $route->setPath(Path::join($prefix, $route->getPath()));
        }

        if ($middlewares = $this->middlewares) {
            $route->setMiddlewares(array_merge($route->getMiddlewares(), $middlewares));
        }

        if ($extras = $this->extras) {
            $route->setExtras(array_merge($route->getExtras(), $extras));
        }
    }

    public function matches(Request $request): bool
    {
        $route = new Route('/{any:any}', fn () => null);
        $this->applyToRoute($route);

        return ($request->getPath() === $this->prefix) || $route->match($request);
    }

    public function addRoutes(Route ...$routes): void
    {
        foreach ($routes as $route) {
            $this->applyToRoute($route);
            $this->routes[] = $route;
        }
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        $routes = [];

        foreach ($this->groups as $group) {
            array_push($routes, ...$group->getRoutes());
        }

        array_push($routes, ...$this->routes);

        return $routes;
    }

    public function &addSubGroup(RouteGroup $paramGroup): RouteGroup
    {
        $addedGroup = $this->mergeWith($paramGroup);
        $this->groups[] = &$addedGroup;

        return $addedGroup;
    }

    public function findMatchingRoute(Request $request, array &$exceptions = []): false|Route
    {
        foreach ($this->routes as $route) {
            try {
                if ($route->match($request)) {
                    return $route;
                }
            } catch (InvalidRequestMethodException $newException) {
                $exceptions[] = $newException;
            }
        }

        foreach ($this->groups as $group) {
            if (!$group->matches($request)) {
                continue;
            }

            return $group->findMatchingRoute($request, $exceptions);
        }

        return false;
    }
}
