<?php

namespace Cube\Web\Http\Rules;

abstract class Rule
{
    const META_TYPE = 'type';
    const META_MIN = 'min-value';
    const META_MAX = 'max-value';
    const META_MODEL = 'model';
    const META_ENUM = 'enum-value';

    /**
     * As Param can be quite generic, this metadata map can hold
     * some additional informations on the parameter such as its type
     */
    protected array $metadata = [];

    /** @var ValidationStep[] */
    protected array $steps = [];

    protected bool $nullable = false;

    /**
     * Add a condition to the Validator, if the callback return `true`, it is considered as valid,
     * otherwise the errorMessage will be displayed to the user.
     */
    public function withCondition(callable $callback, callable|string $errorMessage): static
    {
        $this->steps[] = new ValidationStep(ValidationStep::TYPE_CHECKER, $callback, $errorMessage);

        return $this;
    }

    /**
     * Add a transform step that can be used to edit the value between conditions and/or other transformers.
     */
    public function withTransformer(callable $callback): static
    {
        $this->steps[] = new ValidationStep(ValidationStep::TYPE_TRANSFORMER, $callback);

        return $this;
    }

    public function withValueCondition(callable $callback, callable|string $errorMessage): static
    {
        $wrappedCallback = function ($value) use ($callback) {
            if (null === $value) {
                return true;
            }

            return $callback($value);
        };

        return $this->withCondition($wrappedCallback, $errorMessage);
    }


    protected function withValueTransformer(callable $callback): static
    {
        $wrappedCallback = function ($value) use ($callback) {
            if (null === $value) {
                return null;
            }

            return $callback($value);
        };

        return $this->withTransformer($wrappedCallback);
    }

    /**
     * Given value to replace any incoming `null` value
     */
    public function default(mixed $defaultValue): static
    {
        array_unshift($this->steps, new ValidationStep(
            ValidationStep::TYPE_TRANSFORMER,
            fn (mixed $value) => $value ?? $defaultValue
        ));

        return $this;
    }

    public function validate(mixed $currentValue, ?string $key=null): ValidationReturn
    {
        return $this->runSteps($currentValue, new ValidationReturn(), $key);
    }

    /**
     * The steps stop at the first failed check, so a later step can trust what an earlier one checked
     */
    protected function runSteps(mixed $value, ValidationReturn $return, ?string $key): ValidationReturn
    {
        foreach ($this->steps as $step) {
            $step($value, $return, $key);

            if (!$return->isValid())
                break;
        }

        return $return->setResult($value);
    }

    public function nullable(bool $nullable): static
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function getMetadata(): array 
    {
        return $this->metadata;
    }

}
