<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Env\Session\HasScopedSession;
use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;

abstract class AbstractAuthenticationMiddleware implements Middleware
{
    use HasScopedSession;

    public static function getIdentifier(): string
    {
        return md5(get_called_class());
    }

    public static function handle(Request $request): Request|Response
    {
        /** @var self $self */
        $self = get_called_class();

        $identifier = $self::getIdentifier();

        $route = $request->getRoute();
        $neededPermissions = $route->getExtras()[$identifier];

        $hasPermission = $self::userHasPermission($neededPermissions);

        if ($hasPermission === true)
            return $request;

        return $self::getErrorResponse($hasPermission);
    }

    abstract public static function getUserPermission(): array;

    abstract public static function getErrorResponse(mixed $missingPermissions): Response;

    public static function userHasPermission(mixed $permissions): true|array
    {
        /** @var self $self */
        $self = get_called_class();

        $userPermission = $self::getUserPermission();

        $missingPermissions = array_diff($permissions, $userPermission);

        return count($missingPermissions) ? $missingPermissions : true;
    }

    public static function guard(mixed $neededPermissions, callable $callback, ?Router $router=null)
    {
        $router ??= Router::getInstance();

        /** @var self $self */
        $self = get_called_class();

        $identifier = $self::getIdentifier();

        $router->group("/", [$self], [$identifier => $neededPermissions], $callback);
    }
}
