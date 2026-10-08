<?php

namespace Cube\Data\OpenAPI\Specs;

use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Paths\OASEndPoint;
use Cube\Utils\Console;
use Cube\Utils\Text;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use RuntimeException;

class OASPaths extends AutoDataToObject
{
    public array $groups = [];

    /** @var array<string,string> $operationIds Location of each operation, by operationId */
    protected array $operationIds = [];

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

        $groupedRoutes = Bunch::of($router->getRoutes())
            ->groupBy(fn(Route $route) => $this->getTemplatedPath($route));

        $context = OpenAPIGenerationContext::getInstance();
        $context->log(
            Text::interpolate("Generating OpenAPI json for {count} routes", ['count' => count($router->getRoutes())])
        );

        /** @var Route[] $routes */
        foreach ($groupedRoutes as $path => $routes) {
            foreach ($routes as $route) {
                if (!is_array($route->getCallback())) {
                    $context->log("Ignored closure route : $path");
                    continue;
                }

                foreach ($route->getMethods() as $method) {
                    $method = strtolower($method);

                    $context->log($this->getRouteLogString($method, $route));

                    $this->addOperation($path, $method, $route);

                    $context->log('');
                }
            }
        }
    }

    protected function addOperation(string $path, string $method, Route $route): void
    {
        $operation = (new OASEndPoint($route))->toArray();
        $operationId = $operation['operationId'];
        $location = strtoupper($method).' '.$path;

        if ($existingLocation = $this->operationIds[$operationId] ?? null)
            throw new RuntimeException("Duplicate operationId [{$operationId}] for {$existingLocation} and {$location}, give each its own callback method or #[Endpoint(operationId: ...)]");

        $this->operationIds[$operationId] = $location;
        $this->groups[$path][$method] = $operation;
    }

    protected function getTemplatedPath(Route $route): string
    {
        return Bunch::fromExplode('/', $route->getPath())
            ->map(fn(string $part) => preg_match('/^\{.+:(.+)\}$/U', $part, $match) ? '{' . $match[1] . '}' : $part)
            ->join('/');
    }
}