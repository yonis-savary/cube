<?php

namespace Cube\Web\Router;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Env\Cache;
use Cube\Core\Exceptions\ResponseException;
use Cube\Core\Injector;
use Cube\Web\Http\Exceptions\InvalidRequestMethodException;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Data\Models\Model;
use Cube\Utils\Path;
use Cube\Web\Controller;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\Helpers\WebAPI;

class Router
{
    use Component;

    protected RouterConfiguration $configuration;
    protected RouteGroup $rootHolder;
    protected RouteGroup $currentGroup;

    protected ?Cache $cache = null;
    protected bool $routesAreLoaded = false;

    /** @var WebAPI[] $apis */
    protected array $apis = [];

    public function __construct(RouterConfiguration $config)
    {
        if ($config->cached)
            $this->cache = Cache::getInstance()->child("Routers")->child(md5(static::class));

        $this->rootHolder = new RouteGroup(
            $config->commonPrefix,
            $config->commonMiddlewares,
            []
        );
        $this->currentGroup = $this->rootHolder;
        $this->configuration = $config;
        $this->apis = $this->configuration->apis;
    }

    public function loadRoutes()
    {
        if ($this->routesAreLoaded)
            return;

        $this->routesAreLoaded = true;
        $config = $this->configuration;

        if ($config->loadControllers)
            $this->loadControllers();

        if ($config->loadRoutesFiles)
            $this->loadRoutesFiles();

        foreach($config->apis as $api)
            $this->addService($api);
    }

    public function loadControllers(): void
    {
        $a = Bunch::fromExtends(Controller::class);
        Bunch::fromExtends(Controller::class)
        ->forEach(fn(Controller $class) => $class->routes($this));
    }

    public function loadRoutesFiles(): void
    {
        Bunch::of(Autoloader::getRoutesFiles())
        ->forEach(function(string $file) {
            /** @var Router `$router` variable can be used in routes file */
            $router = $this;
            require Path::relative($file);
        });
    }

    public function addService(WebAPI $api): void
    {
        $api->routes($this);
        $this->apis[] = $api;
    }

    /**
     * @param Route|\Closure(Router)|null ...$routes
     */
    public function addRoutes(Route|callable |null ...$routes): void
    {
        foreach ($routes as $route)
        {
            if ($route === null)
                return;

            if ($route instanceof Route)
                $this->currentGroup->addRoutes($route);
            else
                ($route)($this);
        }
    }

    /**
     * @param array<class-string<Middleware>> $middlewares
     * @param \Closure(Router,RouterGroup)|Route[] $callbackOrRoutes
     */
    public function group(
        string $prefix="/",
        array $middlewares=[],
        array $extras=[],
        ?array $routes=null,
        ?callable $function=null
    ): void
    {
        $subGroup = new RouteGroup($prefix,$middlewares,$extras);

        $parentGroup = $this->currentGroup;
        $this->currentGroup = $this->currentGroup->addSubGroup($subGroup);

        if ($function)
            ($function)($this, $this->currentGroup);

        if ($routes)
            $this->addRoutes(...$routes);

        $this->currentGroup = $parentGroup;
    }

    public function getCachedRouteForRequest(Request $request): Route|false
    {
        if (!$this->cache)
            return false;

        return $this->cache->get($request->getPath(), false);
    }

    public function getRoutes(): array
    {
        return $this->rootHolder->getRoutes();
    }

    protected function globalizeResponse(callable $responseGiver, Request $request): Response
    {
        $callStack = new RouterCallStack($responseGiver, [], $this->rootHolder->getMiddlewares());
        return ($callStack)($request);
    }

    public function findMatchingRoute(Request $request): Route|Response|false
    {
        /** @var InvalidRequestMethodException[] */
        $exceptions = [];

        if ($firstRoute = $this->rootHolder->findMatchingRoute($request, $exceptions))
            return $firstRoute;

        $isOptionsRequest = $request->getMethod() === 'OPTIONS';
        if (count($exceptions) || $isOptionsRequest)
        {
            $allowedMethods = Bunch::of($exceptions)->reduce(
                fn($acc, $exception) => array_merge($acc, $exception->allowedMethods), []
            );

            if ($isOptionsRequest)
                return Response::noContent()->withCORSHeaders($allowedMethods);

            throw new InvalidRequestMethodException($request->getMethod(), $allowedMethods);
        }

        return false;
    }

    public function route(Request $request): Response
    {
        $route = null;

        if (! $route = $this->getCachedRouteForRequest($request))
        {
            $this->loadRoutes();

            foreach($this->apis as $api)
            {
                $serviceResponse = $api->handle($request);
                if ($serviceResponse instanceof Response)
                    return $this->globalizeResponse(fn($r) => $api->handle($r), $request);

                if ($serviceResponse instanceof Route)
                {
                    $route = $serviceResponse;
                    break;
                }
            }

            if (!$route)
            {
                try
                {
                    if (!$routeOrResponse = $this->findMatchingRoute($request))
                        return new Response(StatusCode::NOT_FOUND);

                    if ($routeOrResponse instanceof Response)
                        return $routeOrResponse;

                    $route = $routeOrResponse;
                }
                catch(InvalidRequestMethodException $invalid)
                {
                    return new Response(StatusCode::METHOD_NOT_ALLOWED, $invalid->getMessage());
                }
            }
        }

        try
        {
            $parameters = Injector::getDependencies(
                $route->getCallback(),
                [$request, ...array_values($request->getSlugValues())]
            );

            /** @var Request $request */
            $request = &$parameters[0];

            $parametersReflections = Injector::resolveClosureParameters($route->getCallback());

            $slugValues = $request->getSlugValues();

            for($i=0; $i<min(count($parametersReflections), count($parameters)); $i++) {
                $parameterName = $parametersReflections[$i]->getName();
                $slugValues[$parameterName] = $parameters[$i];
            }

            $request->setSlugObjects($slugValues);

            $response = $route(...$parameters);
        }
        catch(ResponseException $responseException)
        {
            return $responseException->response;
        }

        return $response;
    }
}