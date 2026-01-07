<?php

namespace Cube\Web\Router;

use Cube\Data\Bunch;
use Cube\Data\Models\Model;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;

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

    public function __invoke(Request $request): mixed
    {
        $middleware = $this->getNextMiddleware();

        if ($middleware)
            return $middleware::handle($request, fn(Request $request) => ($this)($request));

        $response = ($this->controllerCallback)($request, ...$this->controllerParams);
        if (! $response instanceof Response)
            $response = $this->adaptControllerReturnToResponse($response);

        if ($response === null)
            $response = new Response();

        return $response;
    }
}