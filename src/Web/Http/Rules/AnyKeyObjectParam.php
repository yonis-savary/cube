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
            ->withValueCondition(fn ($array) => Utils::isAssoc($array), '{key} must be an object, got {value}');
    }

    public function validate(mixed $value, ?string $key=null): ValidationReturn {
        if ($value instanceof Request)
            $value = $value->all();

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

        return $this->transform($return);
    }

    public function nullable(bool $nullable): Rule
    {
        $this->param->nullable($nullable);
        return $this;
    }
}