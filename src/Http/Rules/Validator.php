<?php

namespace YonisSavary\Cube\Http\Rules;

use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Http\Request;

class Validator
{
    /** @var array<string,AbstractRule>|array<AbstractRule> */
    public array $rules = [];

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public static function from(array|Validator|Rule $values): self
    {
        if ($values instanceof Rule)
            return new self(["value"=>$values]);

        if ($values instanceof Validator)
            return $values;

        return new self($values);
    }

    /**
     * @param \Closure(string,AbstractRule) $valueGetter
     */
    protected function genericValidate(callable $valueGetter): true|array
    {
        $validator = $this->rules;

        $errors = [];

        $valid = true;
        /** @var AbstractRule $rule */
        foreach ($validator as $name => $rule)
        {
            $currentValue = $valueGetter($name, $rule);
            if (! $thisOneIsValid = $rule->validateWithSteps($currentValue))
                $errors[$name] = $rule->getErrors($name, $currentValue);

            $valid &= $thisOneIsValid;
        }

        return $valid ? true: $errors;
    }

    public function validateValue(mixed $value): true|array
    {
        return $this->genericValidate(
            fn($_) => $value
        );
    }

    public function validateArray(array $array): true|array
    {
        /** @var AbstractRule $rule */
        return $this->genericValidate(
            fn($name) => $array[$name] ?? null
        );
    }

    public function validateRequest(Request $request): true|array
    {
        return $this->genericValidate(
            function($name, $rule) use ($request) {
                return $rule instanceof UploadRule ?
                    $request->upload($name):
                    $request->param($name);
            }
        );
    }

    public function getLastValues(): array
    {
        /** @var array<string,AbstractRule> $rules */
        $rules = $this->rules;

        return Bunch::unzip($rules)
            ->map(fn($entry) => [$entry[0], $entry[1]->getValue()])
            ->zip();
    }
}