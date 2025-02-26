<?php

namespace Cube\Routine\Cron;

use Cube\Data\Bunch;

class StepValue implements CronValue
{
    public int $step;

    public function __construct(string $rawSet)
    {
        list($step) = Bunch::fromExplode('/', $rawSet)->asIntegers()->get();

        $this->step = $step;
    }

    public static function accepts(string $value): bool
    {
        return preg_match('/^\\*\\/\\d+$/', $value);
    }

    public function matches(int $value): bool
    {
        return 0 === $value % $this->step;
    }

    public function getHeldValues(): array
    {
        return [];
    }
}
