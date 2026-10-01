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

        $neededPermissions = [];
        foreach ($request->getRoute()->getExtras() as $key => $permissions) {
            if (str_starts_with((string) $key, $identifier))
                array_push($neededPermissions, ...$permissions);
        }

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

    public static function guard(mixed $neededPermissions, callable $callback, ?Router $router = null): void
    {
        $router ??= Router::getInstance();

        // One key per permission list, so a nested guard adds to the outer one instead of replacing it
        $identifier = static::getIdentifier().'-'.md5(serialize($neededPermissions));

        $router->group('/', [static::class], [$identifier => $neededPermissions], function: $callback);
    }
}
