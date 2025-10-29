<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Security\Authentication;

trait UserComponent
{
    use Component;

    abstract public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static;

    public static function getAuthentication(): Authentication
    {
        return Authentication::getInstance();
    }

    final public static function getDefaultInstance(): static
    {
        $authentication = self::getAuthentication();
        $userPrimaryKey = $authentication->userId();

        $md5 = md5(
            $userPrimaryKey instanceof \Stringable
            ? $userPrimaryKey
            : serialize($userPrimaryKey)
        );

        return self::getUserInstance($userPrimaryKey, $md5);
    }
}
