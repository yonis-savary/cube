<?php

namespace Cube\Env\Session;

use Cube\Env\Session;

abstract class Straw
{
    public static function getKey(): string
    {
        return md5(get_called_class());
    }

    public static function get(?Session $session = null): mixed
    {
        /** @var self $self */
        $self = get_called_class();
        $session ??= Session::getInstance();

        return $session->get($self::getKey());
    }

    public static function set(mixed $value, ?Session $session = null): void
    {
        /** @var self $self */
        $self = get_called_class();
        $session ??= Session::getInstance();

        $session->set($self::getKey(), $value);
    }
}
