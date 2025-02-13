<?php

namespace YonisSavary\Cube\Routine\Cron;

use InvalidArgumentException;
use YonisSavary\Cube\Data\Bunch;

class RangeOfValues implements CronValue
{
    public static function accepts(string $value): bool
    {
        return preg_match("/^\d+-\d+$/", $value);
    }

    public int $min;
    public int $max;

    public function __construct(string $rawSet)
    {
        list($min, $max) = Bunch::fromExplode("-", $rawSet)->asIntegers()->get();

        if (!($min < $max))
            throw new InvalidArgumentException("Max must be greater than min value (Got min=$min, max=$max)");

        $this->min = $min;
        $this->max = $max;
    }

    public function matches(int $value): bool
    {
        return $this->min <= $value && $value <= $this->max;
    }

    public function getHeldValues(): array
    {
        return range($this->min, $this->max);
    }
}