<?php

namespace Cube\Data\OpenAPI\Specs;

use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Paths\OASEndPoint;
use Cube\Utils\Console;
use Cube\Utils\Path;
use Cube\Utils\Text;
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

    protected function getRouteLogString(string $method, Route $route) {
        $method = strtoupper($method);
        $method = str_pad($method, strlen('OPTIONS'), ' ', STR_PAD_RIGHT);

        $method = match (trim($method)) {
            "GET"     => Console::withGreenColor($method),
            "OPTIONS" => Console::withGreenColor($method),
            "PUT"     => Console::withMagentaColor($method),
            "PATCH"   => Console::withMagentaColor($method),
            "POST"    => Console::withBlueColor($method),
            "DELETE"  => Console::withRedColor($method),
            default   => Console::withGreenColor($method),
        };

        return $method . $route->getPath();
    }

    public function __construct(Router $router)
    {
        // Make sure apis/controllers are loaded
        $router->loadRoutes();
        $root = $router->getRootHolder();

        $groupedRoutes = Bunch::of($this->compileRootHolder($root))
            ->groupBy(fn(Route $route) => $route->getPath());

        $context = OpenAPIGenerationContext::getInstance();
        $context->log(
            Text::interpolate("Generating OpenAPI json for {count} routes", ['count' => count($router->getRoutes())])
        );

        /** @var Route[] $routes */
        foreach ($groupedRoutes as $path => $routes) {
            $this->groups[$path] = [];
            foreach ($routes as $route) {
                if (!is_array($route->getMethods())) {
                    $context->log("Ignored non array route : $path");
                    continue;
                }

                foreach ($route->getMethods() as $method) {
                    $method = strtolower($method);

                    $context->log($this->getRouteLogString($method, $route));

                    $this->groups[$path][$method] = (new OASEndPoint($route))->toArray();

                    $context->log('');
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