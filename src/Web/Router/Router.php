<?php

namespace Cube\Web\Router;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Env\Cache;
use Cube\Http\Exceptions\InvalidRequestException;
use Cube\Http\Exceptions\InvalidRequestMethodException;
use Cube\Http\Request;
use Cube\Http\Response;
use Cube\Http\StatusCode;
use Cube\Models\Model;
use Cube\Web\Controller;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\WebAPI;

use function Cube\debug;

class Router
{
    use Component;

    protected RouterConfiguration $configuration;

    protected RouteGroup $group;

    /**
     * @var array<RouteGroup>
     */
    protected array $groups = [];

    protected ?Cache $cache = null;

    protected bool $routesAreLoaded = false;

    public static function getDefaultInstance(): static
    {
        return new self();
    }

    public function __construct(?RouterConfiguration $config=null)
    {
        $config ??= RouterConfiguration::resolve();

        if ($config->cached)
            $this->cache = Cache::getInstance()->child("Routers")->child(md5(get_called_class()));

        $this->group = new RouteGroup(
            $config->commonPrefix,
            $config->commonMiddlewares,
            []
        );
        $this->groups[] = &$this->group;
        $this->configuration = $config;
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
        Bunch::of(Autoloader::classesThatExtends(Controller::class))
        ->map(fn($class) => new $class)
        ->forEach(fn(Controller $class) => $class->routes($this));
    }

    public function loadRoutesFiles(): void
    {
        Bunch::of(Autoloader::getRoutesFiles())
        ->forEach(function($file) {
            /** @var Router `$router` variable can be used in routes file */
            $router = $this;
            include $file;
        });
    }

    public function addService(WebAPI $api): void
    {
        $api->routes($this);
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
                $this->group->addRoute($route);
            else
                ($route)($this);
        }
    }

    public function group(
        string $prefix="/",
        array $middlewares=[],
        array $extras=[],
        ?callable $callback=null
    ): void
    {
        $subGroup = new RouteGroup($prefix,$middlewares,$extras);

        $oldGroup = &$this->group;
        $newGroup = $this->group->mergeWith($subGroup);
        $this->group = &$newGroup;

        ($callback)($this);

        $this->groups[] = $this->group;
        $this->group = $oldGroup;
    }

    public function getCachedRouteForRequest(Request $request): Route|false
    {
        if (!$this->cache)
            return false;

        return $this->cache->get($request->getPath(), false);
    }

    public function findMatchingRoute(Request $request): Route|false
    {
        $gotInvalidMethod = false;
        $allowedMethods = [];


        $firstRoute = Bunch::of($this->groups)
            ->filter(fn(RouteGroup $group) => $group->matches($request))
            ->map(fn(RouteGroup $group) => $group->getRoutes())
            ->flat()
            ->first(function(Route $route) use ($request, &$gotInvalidMethod, &$allowedMethods) {
                try
                {
                    return $route->match($request);
                }
                catch (InvalidRequestMethodException $invalid)
                {
                    $gotInvalidMethod = true;
                    $allowedMethods = [...$allowedMethods, ...$invalid->allowedMethods];
                    return false;
                }
            });

        if ($firstRoute)
            return $firstRoute;

        if ($gotInvalidMethod)
            throw new InvalidRequestMethodException($request->getMethod(), $allowedMethods);

        return false;
    }

    protected function adaptSingleValue(mixed &$value): mixed
    {
        if ($value instanceof Bunch)
            $value = $value->toArray();

        if ($value instanceof Model)
            $value = $value->toArray();

        if (is_array($value))
            $this->adaptArray($value);

        return $value;
    }

    protected function adaptArray(mixed &$value): array
    {
        foreach ($value as &$row)
            $row = $this->adaptSingleValue($row);

        return $value;
    }

    protected function adaptControllerReturnToResponse(mixed $response): Response
    {
        $response = $this->adaptSingleValue($response);
        return Response::json($response);
    }

    public function route(Request $request): Response
    {
        $route = null;

        if (! $route = $this->getCachedRouteForRequest($request))
        {
            $this->loadRoutes();

            foreach($this->configuration->apis as $api)
            {
                $serviceResponse = $api->handle($request);
                if ($serviceResponse instanceof Response)
                    return $serviceResponse;

                if ($serviceResponse instanceof Route)
                {
                    $route = $serviceResponse;
                    break;
                }
            }

            try
            {
                if ((!$route) && (!$route = $this->findMatchingRoute($request)))
                    return new Response(StatusCode::NOT_FOUND);
            }
            catch(InvalidRequestMethodException $invalid)
            {
                return new Response(StatusCode::METHOD_NOT_ALLOWED, $invalid->getMessage());
            }
        }

        try
        {
            $request = $route->getAppropriateRequestObject($request);
            $response = $route($request);

            if (! $response instanceof Response)
                $response = $this->adaptControllerReturnToResponse($response);

            if ($response === null)
                return new Response();
        }
        catch(InvalidRequestException $invalid)
        {
            $response = new Response(StatusCode::UNPROCESSABLE_CONTENT, json_encode($invalid->errors, JSON_THROW_ON_ERROR));
        }

        return $response;
    }
}