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
    public function default(mixed $defaultValue) {
        array_unshift($this->steps, new ValidationStep(ValidationStep::TYPE_TRANSFORMER, fn() => $defaultValue));
        return $this;
    }

    public function validate(mixed $currentValue, ?string $key=null): ValidationReturn
    {
        $return = new ValidationReturn();
        foreach ($this->steps as $step) {
            $step($currentValue, $return, $key);
        }

        return $return->setResult($currentValue);
    }

    public function nullable(bool $nullable): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    protected function transform(ValidationReturn $return) : ValidationReturn {
        $pointer = $return->getResult();

        foreach ($this->steps as $step) {
            if ($step->type === ValidationStep::TYPE_TRANSFORMER)
                $step($pointer, $return);
        }
        $return->setResult($pointer);

        return $return;
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
