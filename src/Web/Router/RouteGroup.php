<?php

namespace Cube\Web\Router;

use Cube\Data\Bunch;
use Cube\Web\Http\Exceptions\InvalidRequestMethodException;
use Cube\Web\Http\Request;
use Cube\Utils\Path;

class RouteGroup
{
    /** @var array<Route|RouteGroup> */
    protected array $elements = [];

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
            $this->elements[] = $route;
        }
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return Bunch::of($this->elements)
            ->reduce(function($arr, $cur) {
                array_push(
                    $arr,
                    ...($cur instanceof RouteGroup ?
                            $cur->getRoutes():
                            [$cur]
                    )
                );
                return $arr;
            }, []);
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function &addSubGroup(RouteGroup $paramGroup): RouteGroup
    {
        $addedGroup = $this->mergeWith($paramGroup);
        $this->elements[] = &$addedGroup;

        return $addedGroup;
    }

    public function findMatchingRoute(Request $request, array &$exceptions = []): false|Route
    {
        foreach ($this->elements as $routeOrGroup) {
            if ($routeOrGroup instanceof RouteGroup)
            {
                $group = $routeOrGroup;
                if (!$group->matches($request)) {
                    continue;
                }

                if ($route = $group->findMatchingRoute($request, $exceptions))
                    return $route;
            }
            else
            {
                $route = $routeOrGroup;
                try {
                    if ($route->match($request)) {
                        return $route;
                    }
                } catch (InvalidRequestMethodException $newException) {
                    $exceptions[] = $newException;
                }
            }
        }

        return false;
    }
}
