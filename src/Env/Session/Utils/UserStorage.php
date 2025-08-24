<?php

namespace Cube\Env\Session\Utils;

use Cube\Env\Storage;
use Cube\Env\UserComponent;

abstract class UserStorage extends Storage
{
    use UserComponent;

    public static function getUserInstance(mixed $_, string $userPrimaryKeyMD5): static
    {
        $storage = static::getBaseStorage()->child($userPrimaryKeyMD5);

        return new self($storage->getRoot());
    }

    private static function getBaseStorage(): Storage
    {
        $path = strtolower(str_replace('\\', '/', static::class));

        return Storage::getInstance()->child($path);
    }
}
