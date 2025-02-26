<?php

namespace Cube\Routine\Cron;

interface CronValue
{
    public static function accepts(string $value): bool;

    public function matches(int $value): bool;

    public function getHeldValues(): array;
}
