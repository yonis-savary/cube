<?php

namespace Cube\Web\Helpers;

use Closure;
use Cube\Env\Session\HasScopedSession;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;
use Cube\Web\Router\Router;

abstract class AuthenticationMiddleware implements Middleware
{
    use HasScopedSession;

    public static function getIdentifier(): string
    {
        return md5(static::class);
    }

    public static function handle(Request $request, Closure $next): Request|Response
    {
        $identifier = static::getIdentifier();

        $route = $request->getRoute();
        $neededPermissions = $route->getExtras()[$identifier];

        $hasPermission = static::userHasPermission($neededPermissions);

        if (true === $hasPermission) {
            return $next($request);
        }

        return static::getErrorResponse($hasPermission);
    }

    abstract public static function getUserPermission(): array;

    abstract public static function getErrorResponse(mixed $missingPermissions): Response;

    public static function userHasPermission(mixed $permissions): array|true
    {
        $userPermission = static::getUserPermission();

        $missingPermissions = array_diff($permissions, $userPermission);

        return count($missingPermissions) ? $missingPermissions : true;
    }

    public static function guard(mixed $neededPermissions, callable $callback, ?Router $router = null)
    {
        $router ??= Router::getInstance();

        $identifier = static::getIdentifier();

        $router->group('/', [static::class], [$identifier => $neededPermissions], $callback);
    }
}
