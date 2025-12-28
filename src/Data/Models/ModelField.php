<?php

namespace Cube\Data\Models;

use Cube\Utils\Utils;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;

class ModelField
{
    public const FLAG_GENERATED = 0b0000_0001;

    public const STRING = 'STRING';
    public const INTEGER = 'INTEGER';
    public const FLOAT = 'FLOAT';
    public const BOOLEAN = 'BOOLEAN';
    public const DECIMAL = 'DECIMAL';
    public const DATE = 'DATE';
    public const DATETIME = 'DATETIME';
    public const TIMESTAMP = 'TIMESTAMP';

    public const ALLOWED_TYPES = [
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

    public int $flags = 0;

    public null|Model|string $referenceModel = null;
    public ?string $referenceField = null;

    public bool $autoIncrement = false;
    public ?int $maximumLength = null;

    public function __construct(
        public readonly string $name
    ) {}

    public function type(string $type): self
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException('Model field $type must be in '.join(', ', self::ALLOWED_TYPES));
        }

        $this->type = $type;

        return $this;
    }

    public function generated(): self
    {
        $this->flags |= self::FLAG_GENERATED;
        return $this;
    }

    public function isGenerated(): bool
    {
        return Utils::valueHasFlag($this->flags, self::FLAG_GENERATED);
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;

        return $this;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function maximumLength(int $maximumLength): self
    {
        $this->maximumLength = $maximumLength;
        return $this;
    }

    public function hasDefault(bool $hasDefault = true): self
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

    public function hasReference(): bool
    {
        return $this->referenceModel && $this->referenceField;
    }

    public function isInsertable(): bool
    {
        return true;
    }

    public function toPHPExpression(): string
    {
        return
            "(new ModelField('".$this->name."'))"
            ."->type('".$this->type."')"
            .($this->autoIncrement ? '->autoIncrement()' : '')
            .'->nullable('.(($this->nullable && (!$this->autoIncrement)) ? 'true' : 'false').')'
            .'->hasDefault('.($this->hasDefault ? 'true' : 'false').')'
            .($this->referenceModel ? '->references('.$this->referenceModel."::class,'".$this->referenceField."')" : '')
            .($this->isGenerated() ? '->generated()': '')
            .($this->maximumLength ? '->maximumLength('.$this->maximumLength.')': '')
        ;
    }

    public function parse(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        switch ($this->type) {
            case self::INTEGER:
                return (int) $value;

            case self::FLOAT:
                return (float) $value;

            case self::BOOLEAN:
                return in_array(strtolower($value), ['true', '1']);

            case self::DATE:
            case self::DATETIME:
                return new \DateTime($value);

            case self::TIMESTAMP:
                return $value;

            default:
                return $value;
        }
    }

    public function toRule(?bool $forceNullable=null): Rule
    {
        $nullable = is_null($forceNullable) ? $this->nullable : $forceNullable;

        return match ($this->type) {
            self::STRING => Param::string(false, $nullable),
            self::INTEGER => Param::integer($nullable),
            self::FLOAT => Param::float($nullable),
            self::BOOLEAN => Param::boolean(),
            self::DECIMAL => Param::string(true, $nullable),
            self::DATE => Param::date(),
            self::DATETIME => Param::datetime(),
            self::TIMESTAMP => Param::datetime(),
            default => Param::string(true, $nullable),
        };
    }
}
