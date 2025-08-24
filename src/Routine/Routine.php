<?php

namespace Cube\Routine;

abstract class Routine
{
    abstract public static function when(): CronExpression;

    public static function shouldLaunch(\DateTime|string $datetime = 'now'): bool
    {
        return static::when()->matches($datetime);
    }
}
