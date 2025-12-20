<?php

namespace Cube\Web\Http\Rules;

use Cube\Utils\Utils;
use InvalidArgumentException;

class ArrayParam extends Rule
{
    protected Param $param;

    public function __construct(
        protected Rule|array $childRule,
        bool $nullable=false
    )
    {
        $this->childRule = Param::from($childRule, $nullable);
        $this->param = (new Param($nullable))->withValueCondition(fn ($array) => Utils::isList($array), '{key} must be a list, got {value}');
    }

    public function validate(mixed $array, ?string $key=null): ValidationReturn {
        $baseReturn = $this->param->validate($array, $key);
        if (is_null($array) || !$baseReturn->isValid())
            return $baseReturn;

        $return = new ValidationReturn();

        for ($key=0; $key<count($array); $key++){
            $valueReturn = $this->childRule->validate($array[$key] ?? null, $key);
            if (!$valueReturn->isValid())
                $return->addErrorKey($key, $valueReturn->getErrors());
        }
        return $return;
    }
}