<?php

namespace YonisSavary\Cube\Routine\Cron;

use YonisSavary\Cube\Data\Bunch;

class SetOfValues implements CronValue
{
    public static function accepts(string $value): bool
    {
        return preg_match("/^(\d+,)+\d+$/", $value);
    }

    public array $values = [];

    public function __construct(string $rawSet)
    {
        $this->values = Bunch::fromExplode(",", $rawSet)->asIntegers()->get();
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