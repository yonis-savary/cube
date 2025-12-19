<?php

namespace Cube\Web\Http\Rules;

use Cube\Utils\Utils;
use Cube\Web\Http\Request;
use InvalidArgumentException;

class ObjectParam extends Rule
{
    protected Param $param;

    /** @var Array<string,Rule> */
    protected array $rules=[];

    public function __construct(array $assocRules=[], bool $nullable=false)
    {
        if (!Utils::isAssoc($assocRules))
            throw new InvalidArgumentException('Given array must be an associative array');

        $this->rules = $assocRules;
        $this->param = new Param($nullable)
            ->withValueCondition(fn ($array) => Utils::isAssoc($array), '{key} must be an object, got {value}');
    }

    public function validate(mixed $value, ?string $key=null): ValidationReturn {
        if ($value instanceof Request)
            $value = $value->all();

        $baseReturn = $this->param->validate($value, $key);
        if (is_null($value) || !$baseReturn->isValid())
            return $baseReturn;

        $return = new ValidationReturn([]);

        foreach ($this->rules as $ruleKey => $rule) {
            if (is_array($rule))
                $rule = Param::object($rule, false);

            $valueReturn = $rule->validate($value[$ruleKey] ?? null, $ruleKey);

            if ($valueReturn->isValid())
                $return->setResultKey($ruleKey, $valueReturn->getResult());
            else
                $return->addErrorKey($ruleKey, $valueReturn->getErrors());

        }
        return $return;
    }
}