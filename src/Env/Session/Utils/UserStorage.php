<?php

namespace Cube\Env\Session\Utils;

use Cube\Env\Storage;
use Cube\Env\UserComponent;
use RuntimeException;

abstract class UserStorage extends Storage
{
    use UserComponent;

    public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static
    {
        if (false === $userPrimaryKey)
            throw new RuntimeException(static::class.' needs an authenticated user');

        $storage = static::getBaseStorage()->child($userPrimaryKeyMD5);

        return new static($storage->getRoot(), $storage->driver);
    }

    private static function getBaseStorage(): Storage
    {
        $path = strtolower(str_replace('\\', '/', static::class));

        return Storage::getInstance()->child($path);
    }
}
