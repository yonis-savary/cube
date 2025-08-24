<?php

namespace Cube\Env\Session;

use Cube\Env\Session;

abstract class Straw
{
    public static function getKey(): string
    {
        return md5(static::class);
    }

    public static function get(?Session $session = null): mixed
    {
        $session ??= Session::getInstance();
        return $session->get(static::getKey());
    }

    public static function set(mixed $value, ?Session $session = null): void
    {
        $session ??= Session::getInstance();
        $session->set(static::getKey(), $value);
    }
}
