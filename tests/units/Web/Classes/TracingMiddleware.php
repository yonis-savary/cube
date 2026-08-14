<?php

namespace Cube\Tests\Units\Web\Classes;

use Closure;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;

/**
 * Records that it ran, then lets the call stack continue.
 */
class TracingMiddleware implements Middleware
{
    /** @var string[] */
    public static array $trace = [];

    public static function reset(): void
    {
        self::$trace = [];
    }

    public static function handle(Request $request, Closure $next): Request|Response
    {
        self::$trace[] = static::class;

        return $next($request);
    }
}
