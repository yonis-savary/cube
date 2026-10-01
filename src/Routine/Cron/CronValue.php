<?php

namespace Cube\Routine\Cron;

interface CronValue
{
    public static function accepts(string $value): bool;

    /**
     * @param int $fieldMinimum First value of the field `$value` belongs to (0 for minutes, 1 for months...)
     */
    public function matches(int $value, int $fieldMinimum = 0): bool;

    public function getHeldValues(): array;
}
