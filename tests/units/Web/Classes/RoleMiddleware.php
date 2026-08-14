<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Web\Helpers\AuthenticationMiddleware;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;

/**
 * Holds the current user roles in a static so a test can decide who is asking.
 */
class RoleMiddleware extends AuthenticationMiddleware
{
    /** @var string[] */
    public static array $roles = [];

    public static function getUserPermission(): array
    {
        return self::$roles;
    }

    public static function getErrorResponse(mixed $missingPermissions): Response
    {
        return new Response(StatusCode::FORBIDDEN, join(',', $missingPermissions));
    }
}
