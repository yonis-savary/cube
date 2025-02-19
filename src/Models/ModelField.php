<?php

namespace Cube\Models;

use DateTime;
use InvalidArgumentException;
use Cube\Http\Rules\Rule;

class ModelField
{
    const STRING    = "STRING";
    const INTEGER   = "INTEGER";
    const FLOAT     = "FLOAT";
    const BOOLEAN   = "BOOLEAN";
    const DECIMAL   = "DECIMAL";
    const DATE      = "DATE";
    const DATETIME  = "DATETIME";
    const TIMESTAMP = "TIMESTAMP";

    const ALLOWED_TYPES = [
        self::STRING,
        self::INTEGER,
        self::FLOAT,
        self::BOOLEAN,
        self::DECIMAL,
        self::DATE,
        self::DATETIME,
        self::TIMESTAMP,
    ];

    public string $type = self::STRING;
    public bool $nullable = true;
    public bool $hasDefault = true;

    public string|Model|null $referenceModel = null ;
    public ?string $referenceField = null ;

    public bool $autoIncrement = false;

    public function __construct(
        public readonly string $name
    ) {}

    public function type(string $type): self
    {
        if (!in_array($type, self::ALLOWED_TYPES))
            throw new InvalidArgumentException("Model field \$type must be in ". join(", ", self::ALLOWED_TYPES));

        $this->type = $type;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function nullable(bool $nullable=true): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function hasDefault(bool $hasDefault=true): self
    {
        $this->hasDefault = $hasDefault;
        return $this;
    }

    public function references(string $model, string $field): self
    {
        $this->referenceModel = $model;
        $this->referenceField = $field;
        return $this;
    }

    public function isInsertable(): bool
    {
        return true;
    }

    public function toPHPExpression(): string
    {
        return
            "(new ModelField('". $this->name ."'))" .
            "->type('". $this->type ."')" .
            ($this->autoIncrement ? "->autoIncrement()" : '') .
            "->nullable(" . (($this->nullable && (!$this->autoIncrement)) ? "true": "false") . ")" .
            "->hasDefault(" . ($this->hasDefault ? 'true': 'false') . ")" .
            ($this->referenceModel ? "->references(" . $this->referenceModel . "::class,'" . $this->referenceField . "')" : '');
    }


    public function parse(mixed $value): mixed
    {
        if ($value === null)
            return null;

        switch ($this->type)
        {
            case self::INTEGER:
                return (int) $value;
            case self::FLOAT:
                return (float) $value;
            case self::BOOLEAN:
                return in_array(strtolower($value), ['true', '1']);
            case self::DATE:
            case self::DATETIME:
                return new DateTime($value);
            case self::TIMESTAMP:
                return $value;
            default:
                return $value;
        }
    }

    public function toRule(): Rule
    {
        $nullable = $this->nullable;
        $baseRule = match($this->type) {
            self::STRING => Rule::string(false, $nullable),
            self::INTEGER => Rule::integer($nullable),
            self::FLOAT => Rule::float($nullable),
            self::BOOLEAN => Rule::boolean(),
            self::DECIMAL => Rule::string(true, $nullable),
            self::DATE => Rule::date(),
            self::DATETIME => Rule::datetime(),
            self::TIMESTAMP => Rule::datetime(),
            default => Rule::string(true, $nullable),
        };

        return $baseRule;
    }
}