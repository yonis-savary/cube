<?php

namespace YonisSavary\Cube\Routine\Cron;

use InvalidArgumentException;
use YonisSavary\Cube\Data\Bunch;

class StepValue implements CronValue
{
    public static function accepts(string $value): bool
    {
        return preg_match("/^\*\/\d+$/", $value);
    }

    public int $step;

    public function __construct(string $rawSet)
    {
        list($step) = Bunch::fromExplode("/", $rawSet)->asIntegers()->get();

        $this->step = $step;
    }

    public function matches(int $value): bool
    {
        return $value % $this->step === 0;
    }

    public function getHeldValues(): array
    {
        return [];
    }
}