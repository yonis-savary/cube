<?php

namespace Cube\Web\Router;

use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;

class RouterCallStack
{
    /**
     * @var array<class-string<Middleware>>
     */
    protected array $middlewares = [];

    /** @var \Closure(Request):mixed */
    protected \Closure $handler;

    protected int $index = 0;

    /**
     * @param \Closure(Request):mixed $handler
     */
    public function __construct(\Closure $handler, array $middlewares = [])
    {
        $this->middlewares = $middlewares;
        $this->handler = $handler;
    }

    /**
     * @return class-string<Middleware>|false
     */
    public function getNextMiddleware(): string|false
    {
        return $this->middlewares[$this->index++] ?? false;
    }

    protected function adaptValueToResponse(mixed $response): Response
    {
        if ($response instanceof Response)
            return $response;

        if ($response === null)
            return new Response();

        return Response::json($response);
    }

    public function __invoke(Request $request): Response
    {
        $middleware = $this->getNextMiddleware();

        $response = $middleware
            ? $middleware::handle($request, fn(Request $request) => ($this)($request))
            : ($this->handler)($request);

        return $this->adaptValueToResponse($response);
    }
}