<?php

namespace Cube\Env\Session\Utils;

use Cube\Env\Storage;
use Cube\Env\UserComponent;

abstract class UserStorage extends Storage
{
    use UserComponent;

    public static function getUserInstance(mixed $_, string $userPrimaryKeyMD5): static
    {
        /** @var class-string<static> $self */
        $self = get_called_class();

        $storage = $self::getBaseStorage()->child($userPrimaryKeyMD5);

        return new self($storage->getRoot());
    }

    private static function getBaseStorage(): Storage
    {
        $class = get_called_class();
        $path = strtolower(str_replace('\\', '/', $class));

        return Storage::getInstance()->child($path);
    }
}
