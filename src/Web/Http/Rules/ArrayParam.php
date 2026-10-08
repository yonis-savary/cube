<?php

namespace Cube\Web\Http\Rules;

use Cube\Utils\Utils;

class ArrayParam extends Rule
{
    protected Param $param;

    public function __construct(
        protected Rule|array $childRule,
        bool $nullable=false
    )
    {
        $this->childRule = Param::from($childRule, $nullable);
        $this->param = (new Param($nullable))->withValueCondition(fn ($array) => is_array($array) && Utils::isList($array), '{key} must be a list, got {value}');
    }

    public function maxSize(int $maxSize): static
    {
        $this->param->withValueCondition(fn($value) => is_array($value) ? count($value) <= $maxSize : true, "{key} must be an array with a maximum of $maxSize items");
        return $this;
    }

    public function minSize(int $minSize): static
    {
        $this->param->withValueCondition(fn($value) => is_array($value) ? count($value) >= $minSize : true, "{key} must be an array with at least $minSize items");
        return $this;
    }

    public function size(int $size): static
    {
        $this->param->withValueCondition(fn($value) => is_array($value) ? count($value) === $size : true, "{key} must be an array with exactly $size items");
        return $this;
    }

    public function validate(mixed $array, ?string $key=null): ValidationReturn {
        $baseReturn = $this->param->validate($array, $key);
        if (is_null($array) || !$baseReturn->isValid()) {
            return $baseReturn;
        }

        $return = new ValidationReturn([]);

        for ($index=0; $index<count($array); $index++){
            $valueReturn = $this->childRule->validate($array[$index] ?? null, "{$key}.{$index}");
            if ($valueReturn->isValid())
                $return->pushResult($valueReturn->getResult());
            else
                $return->addErrorKey($index, $valueReturn->getErrors());
        }

        return $return->isValid()
            ? $this->runSteps($return->getResult(), $return, $key)
            : $return;
    }

    public function nullable(bool $nullable): static
    {
        $this->param->nullable($nullable);
        return $this;
    }

    public function isNullable(): bool
    {
        return $this->param->isNullable();
    }

    public function getChildRule(): Rule
    {
        return $this->childRule;
    }
}