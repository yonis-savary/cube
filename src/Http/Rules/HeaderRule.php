<?php

namespace YonisSavary\Cube\Http\Rules;

class HeaderRule extends AbstractRule
{
    public function validate(mixed $value): bool
    {
        return true;
    }
}