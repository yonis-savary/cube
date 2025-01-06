<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;

class Route
{
    const SLUG_FORMATS = [
        'int'      => "\d+",
        'float'    => "\d+(?:\.\d+)?",
        'any'      => '.+',
        'date'     => "\d{4}\-\d{2}\-\d{2}",
        'time'     => "\d{2}\:\d{2}\:\d{2}",
        'datetime' => "\d{4}\-\d{2}\-\d{2} \d{2}\:\d{2}\:\d{2}",
        'hex'      => '[0-9a-fA-F]+',
        'uuid'     => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'
    ];

    protected $callback;

    protected string $path;
    protected ?array $methods=[];
    protected ?array $extras=[];

    /** @var array<Middleware> $middlewares */
    protected array $middlewares = [];


    public function __construct(
        string $path,
        callable $callback,
        array $methods=[],
        array $middlewares=[],
        array $extras=[]
    )
    {
        if (str_ends_with($path, "/"))
            $path = substr($path, 0, strlen($path)-2);

        $this->path = $path;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->middlewares = $middlewares;
        $this->extras = $extras;
    }


    public function isCachable(): bool
    {
        return is_array($this->callback);
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function setPath(string $path): void { $this->path = $path; }
    public function getPath(): string { return $this->path; }

    public function setMethods(array $methods): void { $this->methods = $methods; }
    public function getMethods(): array { return $this->methods; }

    public function setMiddlewares(array $middlewares): void { $this->middlewares = $middlewares = $middlewares; }
    public function getMiddlewares(): array { return $this->middlewares; }

    public function setExtras(array $extras): void { $this->extras = $extras = $extras; }
    public function getExtras(): array { return $this->extras; }


    protected function matchPathRegex(Request $request): string
    {
        $regexMap = [];
        $parts = explode('/', $this->getPath());

        foreach ($parts as &$part)
        {
            if (!preg_match("/^\{.+\}$/", $part))
                continue;

            $part = substr($part, 1, strlen($part)-2);

            $name = $part;
            $expression = "[^\\/]+";

            if (str_contains($part, ':'))
            {
                list($type, $name) = explode(':', $part, 2);
                $expression = self::SLUG_FORMATS[$type] ?? $type;
            }

            $regexMap[] = $name;
            $part = "($expression)";
        }

        $regex = '/^'. join("\\/", $parts) ."$/";

        if (!preg_match($regex, $request->getPath(), $slugs))
            return false;

        $namedSlugs = [];
        array_shift($slugs);
        for ($i=0; $i<count($slugs); $i++)
            $namedSlugs[$regexMap[$i]] = urldecode($slugs[$i]);

        $request->setSlugValues($namedSlugs);
        return true;
    }

    public function match(Request $request): bool
    {
        $methods = $this->getMethods();
        $needMethod = count($methods) > 0;
        if ($needMethod && (!in_array($request->getMethod(), $methods)))
            return false;

        $routePath = $this->getPath();
        $requestPath = $request->getPath();

        // Little optimization: if the route has no slug
        // we can just compare strings, no need to process anything
        if (!str_contains($routePath, '{'))
            return $routePath === $requestPath;

        return $this->matchPathRegex($request);
    }


    public function __invoke(Request $request): mixed
    {
        $request->setRoute($this);

        foreach ($this->middlewares as $middleware)
        {
            $middlewareResponse = $middleware::handle($request);

            if ($middlewareResponse instanceof Response)
                return $middlewareResponse;

            $request = $middlewareResponse;
        }

        return ($this->callback)($request, ...array_values($request->getSlugValues()));
    }
}