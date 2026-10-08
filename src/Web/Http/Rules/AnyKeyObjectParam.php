<?php

namespace Cube\Web\Http\Rules;

use Cube\Utils\Utils;
use Cube\Web\Http\Request;

class AnyKeyObjectParam extends Rule
{
    protected Param $param;

    protected Rule $childRule;

    public function __construct(
        Rule|array $childRule,
        bool $nullable=false
    )
    {
        $this->childRule = Param::from($childRule, $nullable);
        $this->param = (new Param($nullable))
            ->withValueCondition(fn ($array) => is_array($array) && Utils::isAssoc($array), '{key} must be an object, got {value}');
    }

    public function validate(mixed $value, ?string $key=null): ValidationReturn {
        if ($value instanceof Request)
            $value = $value->all();

        if (null === $value && $this->param->isNullable())
            return new ValidationReturn();

        $value ??= [];
        $baseReturn = $this->param->validate($value, $key);
        if (!$baseReturn->isValid())
            return $baseReturn;

        $return = new ValidationReturn([]);

        $childRule = $this->childRule;

        foreach ($value as $subKey => $subValue) {
            $valueReturn = $childRule->validate($subValue ?? null, $subKey);
    
            if ($valueReturn->isValid())
                $return->setResultKey($subKey, $valueReturn->getResult());
            else
                $return->addErrorKey("$key.$subKey", $valueReturn->getErrors());
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
}