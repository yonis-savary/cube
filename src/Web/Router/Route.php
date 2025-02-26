<?php

namespace Cube\Web\Router;

use Cube\Core\Autoloader;
use Cube\Http\Exceptions\InvalidRequestException;
use Cube\Http\Exceptions\InvalidRequestMethodException;
use Cube\Http\Request;
use Cube\Http\Response;

class Route
{
    public const SLUG_FORMATS = [
        'int' => '\d+',
        'float' => '\d+(?:\.\d+)?',
        'any' => '.+',
        'date' => '\d{4}\-\d{2}\-\d{2}',
        'time' => '\d{2}\:\d{2}\:\d{2}',
        'datetime' => '\d{4}\-\d{2}\-\d{2} \d{2}\:\d{2}\:\d{2}',
        'hex' => '[0-9a-fA-F]+',
        'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
    ];

    protected $callback;

    protected string $path;
    protected ?array $methods = [];
    protected ?array $extras = [];

    /** @var array<Middleware> */
    protected array $middlewares = [];

    public function __construct(
        string $path,
        callable $callback,
        array $methods = [],
        array $middlewares = [],
        array $extras = []
    ) {
        if (str_ends_with($path, '/')) {
            $path = substr($path, 0, strlen($path) - 2);
        }

        $this->path = $path;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->middlewares = $middlewares;
        $this->extras = $extras;
    }

    public function __invoke(Request $request): mixed
    {
        $request->setRoute($this);

        foreach ($this->middlewares as $middleware) {
            $middlewareResponse = $middleware::handle($request);

            if ($middlewareResponse instanceof Response) {
                return $middlewareResponse;
            }

            $request = $middlewareResponse;
        }

        return ($this->callback)($request, ...array_values($request->getSlugValues()));
    }

    public static function any(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, [], $middlewares, $extras);
    }

    public static function get(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, ['GET'], $middlewares, $extras);
    }

    public static function post(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, ['POST'], $middlewares, $extras);
    }

    public static function put(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, ['PUT'], $middlewares, $extras);
    }

    public static function patch(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, ['PATCH'], $middlewares, $extras);
    }

    public static function delete(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, ['DELETE'], $middlewares, $extras);
    }

    public static function option(string $path, callable $callback, array $middlewares = [], array $extras = [])
    {
        return new self($path, $callback, ['OPTION'], $middlewares, $extras);
    }

    public function isCachable(): bool
    {
        return is_array($this->callback);
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setMethods(array $methods): void
    {
        $this->methods = $methods;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function setMiddlewares(array $middlewares): void
    {
        $this->middlewares = $middlewares = $middlewares;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function setExtras(array $extras): void
    {
        $this->extras = $extras = $extras;
    }

    public function getExtras(): array
    {
        return $this->extras;
    }

    public function match(Request $request): bool
    {
        $routePath = $this->getPath();
        $requestPath = $request->getPath();

        $pathMatches = false;
        // Little optimization: if the route has no slug
        // we can just compare strings, no need to process anything
        $pathMatches = str_contains($routePath, '{')
            ? $this->matchPathRegex($request)
            : $routePath === $requestPath;

        if (!$pathMatches) {
            return false;
        }

        $methods = $this->getMethods();
        $needMethod = count($methods) > 0;
        if ($needMethod && (!in_array($request->getMethod(), $methods))) {
            throw new InvalidRequestMethodException($request->getMethod(), $methods);
        }

        return true;
    }

    public function getAppropriateRequestObject(Request $defaultRequest): Request
    {
        $callback = $this->callback;

        if (is_array($callback)) {
            $controller = new \ReflectionClass($callback[0]);
            $reflection = $controller->getMethod($callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }
        $parameters = $reflection->getParameters();

        if (!count($parameters)) {
            return $defaultRequest;
        }

        $type = $parameters[0]->getType();
        $requestType = $type ? $type->getName() : Request::class;

        if (!Autoloader::extends($requestType, Request::class)) {
            throw new \InvalidArgumentException("A controller function first paramerter must be a Request object, got {$requestType}");
        }

        /** @var Request $requestType */
        $request = $requestType::fromRequest($defaultRequest);

        $result = $request->isValid();
        if (true !== $result) {
            throw new InvalidRequestException($result, $request);
        }

        return $request;
    }

    protected function matchPathRegex(Request $request): string
    {
        $regexMap = [];
        $parts = explode('/', $this->getPath());

        foreach ($parts as &$part) {
            if (!preg_match('/^\{.+\}$/', $part)) {
                continue;
            }

            $part = substr($part, 1, strlen($part) - 2);

            $name = $part;
            $expression = '[^\/]+';

            if (str_contains($part, ':')) {
                list($type, $name) = explode(':', $part, 2);
                $expression = self::SLUG_FORMATS[$type] ?? $type;
            }

            $regexMap[] = $name;
            $part = "({$expression})";
        }

        $regex = '/^'.join('\/', $parts).'$/';

        if (!preg_match($regex, $request->getPath(), $slugs)) {
            return false;
        }

        $namedSlugs = [];
        array_shift($slugs);
        for ($i = 0; $i < count($slugs); ++$i) {
            $namedSlugs[$regexMap[$i]] = urldecode($slugs[$i]);
        }

        $request->setSlugValues($namedSlugs);

        return true;
    }
}
