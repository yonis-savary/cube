<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Env\Cache;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;
use YonisSavary\Cube\Http\StatusCode;
use YonisSavary\Cube\Utils\Path;
use YonisSavary\Cube\Web\Router\RouterConfiguration;
use YonisSavary\Cube\Web\Router\Service;

class Router
{
    use Component;

    protected ?string $groupUrlPrefix = null;
    protected array $groupMiddlewares = [];
    protected array $groupExtras = [];

    /**
     * @var array<Route>
     */
    protected array $routes = [];

    protected ?Cache $cache = null;

    public static function getDefaultInstance(): static
    {
        return new self();
    }

    public function __construct(?RouterConfiguration $config=null)
    {
        $config ??= RouterConfiguration::resolve();

        if ($config->cached)
        $this->cache = Cache::getInstance()->child("Routers")->child(md5(get_called_class()));

        if ($middlewares = $config->commonMiddlewares)
            $this->groupMiddlewares = $middlewares;

        if ($prefix = $config->commonPrefix)
            $this->groupUrlPrefix = $prefix;

        if ($config->loadControllers)
            $this->loadControllers();

        if ($config->loadRoutesFiles)
            $this->loadRoutesFiles();

        foreach($config->services as $service)
            $this->loadService($service);
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

    public function loadService(Service $service): void
    {
        $service->routes($this);
    }



    public function addRoutes(Route ...$routes): void
    {
        foreach ($routes as $route)
        {
            if ($prefix = $this->groupUrlPrefix)
                $route->setPath(Path::join($route->getPath(), $prefix));

            if ($middlewares = $this->groupMiddlewares)
                $route->setMiddlewares(array_merge($route->getMiddlewares(), $middlewares));

            if ($extras = $this->groupExtras)
                $route->setExtras(array_merge($route->getExtras(), $extras));

            $this->routes[] = $route;
        }
    }

    public function group(
        string $prefix="/",
        array $middlewares=[],
        array $extras=[],
        ?callable $callback=null
    ): void
    {
        $oldUrlPrefix = $this->groupUrlPrefix;
        $oldMiddlewares = $this->groupMiddlewares;
        $oldExtras = $this->groupExtras;

        $this->groupUrlPrefix = Path::join($this->groupUrlPrefix, $prefix);
        $this->groupMiddlewares = array_merge($this->groupMiddlewares, $middlewares);
        $this->groupExtras = array_merge($this->groupExtras, $extras);

        $callback($this);

        $this->groupUrlPrefix = $oldUrlPrefix;
        $this->groupMiddlewares = $oldMiddlewares;
        $this->groupExtras = $oldExtras;
    }

    public function findMatchingRoute(Request $request): Route|false
    {
        return Bunch::of($this->routes)
            ->first(fn(Route $route) => $route->match($request)) ?? false;
    }

    public function route(Request $request): Response
    {
        if (!$route = $this->findMatchingRoute($request))
            return new Response(StatusCode::NOT_FOUND);

        $response = $route($request);
        if ($response === null)
            return new Response();

        return $response;
    }
}