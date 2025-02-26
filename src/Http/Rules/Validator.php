<?php

namespace Cube\Http\Rules;

use Cube\Data\Bunch;
use Cube\Http\Request;

class Validator
{
    /** @var array<Rule>|array<string,Rule> */
    public array $rules = [];

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public static function from(array|Rule|Validator $values): self
    {
        if ($values instanceof Rule) {
            return new self(['value' => $values]);
        }

        if ($values instanceof Validator) {
            return $values;
        }

        return new self($values);
    }

    public function validateValue(mixed $value): array|true
    {
        return $this->genericValidate(
            fn ($_) => $value
        );
    }

    public function validateArray(array $array): array|true
    {
        // @var Rule $rule
        return $this->genericValidate(
            fn ($name) => $array[$name] ?? null
        );
    }

    public function validateRequest(Request $request): array|true
    {
        return $this->genericValidate(
            function ($name, $rule) use ($request) {
                return $rule instanceof UploadRule
                    ? $request->upload($name)
                    : $request->param($name);
            }
        );
    }

    public function getLastValues(): array
    {
        /** @var array<string,Rule> $rules */
        $rules = $this->rules;

        return Bunch::unzip($rules)
            ->map(fn ($entry) => [$entry[0], $entry[1]->getValue()])
            ->zip()
        ;
    }

    /**
     * @param \Closure(string,Rule) $valueGetter
     */
    protected function genericValidate(callable $valueGetter): array|true
    {
        $validator = $this->rules;

        $errors = [];

        $valid = true;

        /** @var Rule $rule */
        foreach ($validator as $name => $rule) {
            $currentValue = $valueGetter($name, $rule);
            if (!$thisOneIsValid = $rule->validateWithSteps($currentValue)) {
                $errors[$name] = $rule->getErrors($name, $currentValue);
            }

            $valid &= $thisOneIsValid;
        }

        return $valid ? true : $errors;
    }
}
