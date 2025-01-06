<?php

namespace YonisSavary\Cube\Http\Rules;

abstract class AbstractRule
{
    protected mixed $value = null;

    public abstract function validate(mixed $value): bool;

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}