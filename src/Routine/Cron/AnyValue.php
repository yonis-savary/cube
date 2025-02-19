<?php

namespace Cube\Routine\Cron;

class AnyValue implements CronValue
{
    public static function accepts(string $value): bool
    {
        return $value === "*";
    }

    public function matches(int $value): bool
    {
        return true;
    }

    public function getHeldValues(): array
    {
        return [];
    }
}