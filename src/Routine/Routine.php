<?php

namespace Cube\Routine;

abstract class Routine
{
    abstract public static function when(): CronExpression;

    public static function shouldLaunch(\DateTime|string $datetime = 'now'): bool
    {
        /** @var self $self */
        $self = get_called_class();

        return $self::when()->matches($datetime);
    }
}
