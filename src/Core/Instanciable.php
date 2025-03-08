<?php

namespace Cube\Core;

trait Instanciable
{
    public static function instanciate(...$params): self
    {
        return Autoloader::instanciate(self::class, $params);
    }
}