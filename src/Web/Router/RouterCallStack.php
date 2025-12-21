<?php

namespace Cube\Web\Router;

use Cube\Core\Injector;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;
use Generator;

class RouterCallStack
{
    /**
     * @var array<class-string<Middleware>>
     */
    protected array $middlewares = [];

    protected mixed $controllerCallback;
    protected mixed $controllerParams;

    protected int $index = 0;

    public function __construct(
        callable $controllerCallback,
        mixed $controllerParams=[],
        array $middlewares = [],
    )
    {
        $this->middlewares = $middlewares;
        $this->controllerParams = $controllerParams;
        $this->controllerCallback = $controllerCallback;
    }

    /**
     * @return class-string<Middleware>|false
     */
    public function getNextMiddleware(): string|false
    {
        return $this->middlewares[$this->index++] ?? false;
    }

    public function __invoke(Request $request): mixed
    {
        $middleware = $this->getNextMiddleware();

        if ($middleware)
            return $middleware::handle($request, fn(Request $request) => ($this)($request));

        return ($this->controllerCallback)($request, ...$this->controllerParams);
    }
}