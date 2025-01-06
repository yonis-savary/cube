<?php

namespace YonisSavary\Cube\Http\Rules;

class UploadRule extends AbstractRule
{
    public function validate(mixed $value): bool
    {
        return true;
    }
}