<?php

namespace Cube\Routine\Cron;

class SpecificValue implements CronValue
{
    public static function accepts(string $value): bool
    {
        return is_numeric($value);
    }

    public function __construct(
        public readonly int $value
    ){}

    public function matches(int $value): bool
    {
        return $value === $this->value;
    }

    public function getHeldValues(): array
    {
        return [$this->value];
    }
}