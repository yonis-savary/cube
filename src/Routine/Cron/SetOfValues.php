<?php

namespace Cube\Routine\Cron;

use Cube\Data\Bunch;

class SetOfValues implements CronValue
{
    public array $values = [];

    public function __construct(string $rawSet)
    {
        $this->values = Bunch::fromExplode(',', $rawSet)->asIntegers()->get();
    }

    public static function accepts(string $value): bool
    {
        return preg_match('/^(\d+,)+\d+$/', $value);
    }

    public function matches(int $value): bool
    {
        return in_array($value, $this->values);
    }

    public function getHeldValues(): array
    {
        return $this->values;
    }
}
