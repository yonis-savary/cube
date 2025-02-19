<?php

namespace Cube\Http\Rules;

use Cube\Data\Bunch;
use Cube\Http\Request;

class Validator
{
    /** @var array<string,Rule>|array<Rule> */
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
     * @param \Closure(string,Rule) $valueGetter
     */
    protected function genericValidate(callable $valueGetter): true|array
    {
        $validator = $this->rules;

        $errors = [];

        $valid = true;
        /** @var Rule $rule */
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
        /** @var Rule $rule */
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
        /** @var array<string,Rule> $rules */
        $rules = $this->rules;

        return Bunch::unzip($rules)
            ->map(fn($entry) => [$entry[0], $entry[1]->getValue()])
            ->zip();
    }
}