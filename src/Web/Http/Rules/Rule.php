<?php

namespace Cube\Web\Http\Rules;

use Cube\Data\Bunch;
use Cube\Utils\Text;

abstract class Rule
{
    private const TYPE_CHECKER = 'check';
    private const TYPE_TRANSFORMER = 'transform';

    protected mixed $value = null;
    protected array $errors = [];
    protected array $steps = [];

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function addError(array|string $error): self
    {
        array_push(
            $this->errors,
            ...Bunch::of($error)->toArray()
        );

        return $this;
    }

    final public function failWithError(string $error): false
    {
        $this->addError($error);

        return false;
    }

    public function getErrors(string $keyName, mixed $value)
    {
        return Bunch::of($this->errors)
            ->map(fn ($x) => is_string($x) ? Text::interpolate($x, ['key' => $keyName, 'value' => print_r($value, true)]) : $x)
            ->toArray()
        ;
    }

    /**
     * Add a condition to the Validator, if the callback return `true`, it is considered as valid,
     * otherwise the errorMessage will be displayed to the user.
     */
    public function withCondition(callable $callback, callable|string $errorMessage): self
    {
        $this->steps[] = [self::TYPE_CHECKER, $callback, $errorMessage];

        return $this;
    }

    public function withValueCondition(callable $callback, callable|string $errorMessage): self
    {
        $wrappedCallback = function ($value) use ($callback) {
            if (null === $value) {
                return true;
            }

            return $callback($value);
        };

        return $this->withCondition($wrappedCallback, $errorMessage);
    }

    /**
     * Add a transform step that can be used to edit the value between conditions and/or other transformers.
     */
    public function withTransformer(callable $callback): self
    {
        $this->steps[] = [self::TYPE_TRANSFORMER, $callback];

        return $this;
    }

    public function withValueTransformer(callable $callback): self
    {
        $wrappedCallback = function ($value) use ($callback) {
            if (null === $value) {
                return null;
            }

            return $callback($value);
        };

        return $this->withTransformer($wrappedCallback);
    }

    public function validateWithSteps(mixed $currentValue): bool
    {
        $isValid = true;

        foreach ($this->steps as $step) {
            list($type, $callback) = $step;
            $errorMessageOrGetter = $step[2] ?? null;

            if (self::TYPE_CHECKER === $type) {
                $stepResult = $callback($currentValue);
                $isValid &= (true === $stepResult);

                if (true !== $stepResult) {
                    if (is_callable($errorMessageOrGetter)) {
                        $errorMessageOrGetter = $errorMessageOrGetter($currentValue);
                    }

                    $this->addError($errorMessageOrGetter);
                }
            } elseif (self::TYPE_TRANSFORMER === $type) {
                $currentValue = ($callback)($currentValue);
            }

            if (!$isValid) {
                break;
            }
        }

        $this->setValue($currentValue);

        return $isValid;
    }
}
