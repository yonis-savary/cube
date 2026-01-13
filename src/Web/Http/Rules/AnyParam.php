<?php

namespace Cube\Web\Http\Rules;

class AnyParam extends Rule
{
    public function validate(mixed $currentValue, ?string $key = null): ValidationReturn
    {
        $return = new ValidationReturn();
        $return->setResult($currentValue);
        return $return;
    }
}