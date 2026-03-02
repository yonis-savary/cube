<?php

namespace Cube\Data\OpenAPI\Specs;

use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Paths\OASEndPoint;
use Cube\Utils\Path;
use Cube\Web\Router\Route;
use Cube\Web\Router\RouteGroup;
use Cube\Web\Router\Router;

class OASPaths extends AutoDataToObject
{
    public array $groups = [];

    public function toArray(): array
    {
        return $this->groups;
    }

    public function __construct(Router $router)
    {
        // Make sure apis/controllers are loaded
        $router->loadRoutes();
        $root = $router->getRootHolder();

        $groupedRoutes = Bunch::of($this->compileRootHolder($root))
            ->groupBy(fn(Route $route) => $route->getPath());

        /** @var Route[] $routes */
        foreach ($groupedRoutes as $path => $routes) {
            $this->groups[$path] = [];
            foreach ($routes as $route) {
                if (!is_array($route->getMethods())) {
                    // TODO Add Log - Ignore non-array callback routes !
                    continue;
                }

                foreach ($route->getMethods() as $method) {
                    $method = strtolower($method);
                    // TODO Add a message in case of duplicated path/method
                    $this->groups[$path][$method] = (new OASEndPoint($route))->toArray();
                }
            }
        }
    }

    /**
     * @return array<string, Route>
     */
    protected function compileRootHolder(RouteGroup $group, string $parentPrefix="/"): array
    {
        $compiled = [];
        foreach ($group->getElements() as $routeOrGroup) {
            if ($routeOrGroup instanceof RouteGroup) {
                $routePrefix = Path::join($parentPrefix, $routeOrGroup->prefix);
                array_push($compiled, ...$this->compileRootHolder($routeOrGroup, $routePrefix));
            }
            else
            {
                $route = &$routeOrGroup;

                $newPath = Path::join($parentPrefix, $route->getPath());
                $route->setPath($newPath);
                $compiled[] = $route;
            }
        }

        return $compiled;
    }
}