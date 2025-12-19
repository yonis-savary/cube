<?php

namespace Cube\Web\Http\Rules;

class ValidationReturn
{
    protected array $errors = [];
    protected mixed $result;

    public function __construct(mixed $result=null)
    {
        $this->result = $result;
    }

    public function setResult(mixed $value): static
    {
        $this->result = $value;
        return $this;
    }

    public function setResultKey(string $key, mixed $value): static 
    {
        $this->result[$key] = $value;
        return $this;
    }

    public function getResult(): mixed 
    {
        return $this->result;
    }

    public function addError(string $error): static
    {
        $this->errors[] = $error;
        return $this;
    }

    public function addErrorKey(string $key, array $errors): static 
    {
        $this->errors[$key] = $errors;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }

    public function mergeWith(ValidationReturn $merge): static {
        array_push($this->errors, ...$merge->getErrors());
        return $this;
    }
}