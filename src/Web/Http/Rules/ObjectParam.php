<?php

namespace Cube\Web\Http\Rules;

use Cube\Data\Bunch;
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
        $this->param = (new Param($nullable))
            ->withValueCondition(fn ($array) => Utils::isAssoc($array), '{key} must be an object, got {value}');
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function validate(mixed $value, ?string $key=null): ValidationReturn {
        if ($value instanceof Request)
            $value = $value->all();

        $value ??= [];
        $baseReturn = $this->param->validate($value, $key);
        if (!$baseReturn->isValid())
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
        return $this->transform($return);
    }

    public function without(array|string $paramsToDelete): static {
        foreach (Utils::toArray($paramsToDelete) as $param) {
            if (array_key_exists($param, $this->rules))
                unset($this->rules[$param]);
        }
        return $this;
    }

    public function optionnal(string|array $keys): static {
        Bunch::of($keys)
        ->filter(fn($key) => array_key_exists($key, $this->rules))
        ->forEach(fn($key) => $this->rules[$key]->nullable(true));

        return $this;
    }

    public function mandatory(string|array $keys): static {
        Bunch::of($keys)
        ->filter(fn($key) => array_key_exists($key, $this->rules))
        ->forEach(fn($key) => $this->rules[$key]->nullable(false));

        return $this;
    }

    public function with(array $newRules): static {
        if (!Utils::isAssoc($newRules))
            throw new InvalidArgumentException("Given rules must be an associative array as name=>rule");

        $this->rules = array_merge($this->rules, $newRules);
        return $this;
    }

    public function nullable(bool $nullable): Rule
    {
        $this->param->nullable($nullable);
        return $this;
    }
}