<?php

namespace Cube\Routine\Cron;

use Cube\Data\Bunch;

class StepValue implements CronValue
{
    public int $step;

    public function __construct(string $rawSet)
    {
        list($step) = Bunch::fromExplode('/', $rawSet)->asIntegers()->get();

        if ($step < 1) {
            throw new \InvalidArgumentException("A step must be greater than zero (got [{$rawSet}])");
        }

        $this->step = $step;
    }

    public static function accepts(string $value): bool
    {
        return (bool) preg_match('/^\*\/\d+$/', $value);
    }

    public function matches(int $value, int $fieldMinimum = 0): bool
    {
        return 0 === ($value - $fieldMinimum) % $this->step;
    }

    public function getHeldValues(): array
    {
        return [$this->step];
    }
}
