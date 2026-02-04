<?php

namespace Cube\Data\Models;

use Cube\Utils\Utils;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Rules\Rule;
use Exception;

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
    public bool $hasDefault = false;
    public mixed $default = null;

    public int $flags = 0;

    public bool $isPrimaryKey = false;

    public null|Model|string $referenceModel = null;
    public ?string $referenceField = null;

    public bool $autoIncrement = false;
    public ?int $maximumLength = null;

    public ?int $decimalMaximumDigits = null;
    public ?int $decimalDigitsToTheRight = null;

    public ?bool $isUnique = null;

    public static function string(string $name, ?int $maximumLength=null) {
        return (new self($name))->type(self::STRING)->maximumLength($maximumLength);
    }

    public static function id(string $name="id") {
        return static::integer($name)->autoIncrement()->primaryKey();
    }

    public static function integer(string $name) {
        return (new self($name))->type(self::INTEGER);
    }

    public static function float(string $name) {
        return (new self($name))->type(self::FLOAT);
    }

    public static function boolean(string $name) {
        return (new self($name))->type(self::BOOLEAN);
    }

    public static function decimal(string $name, ?int $maxiumDigits=null, ?int $precisionToTheRight=null) {
        return (new self($name))->type(self::DECIMAL)->precision($maxiumDigits, $precisionToTheRight);
    }

    public static function date(string $name) {
        return (new self($name))->type(self::DATE);
    }

    public static function datetime(string $name) {
        return (new self($name))->type(self::DATETIME);
    }

    public static function timestamp(string $name) {
        return (new self($name))->type(self::TIMESTAMP);
    }

    public function __construct(
        public string $name
    ) {}

    public function primaryKey(bool $isPrimaryKey = true): self
    {
        $this->isPrimaryKey = $isPrimaryKey;
        $this->notNull();
        return $this;
    }

    public function unique(): self
    {
        $this->isUnique = true;
        return $this;
    }

    public function type(string $type): self
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException('Model field $type must be in '.join(', ', self::ALLOWED_TYPES));
        }

        $this->type = $type;

        return $this;
    }

    /**
     * @param int $m Maximum number of digit
     * @param int $d Digits to the right of the decimal point
     */
    public function precision(int $m=10, int $d=5): self
    {
        if ($d > $m)
            throw new Exception("Decimal precision to the right (d) cannot exceed the maximum number of digit (m)");

        $this->decimalMaximumDigits = $m;
        $this->decimalDigitsToTheRight = $d;
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

    public function notNull(): self
    {
        return $this->nullable(false);
    }

    public function maximumLength(?int $maximumLength=null): self
    {
        $this->maximumLength = $maximumLength;
        return $this;
    }

    public function default(mixed $defaultValue): self 
    {
        $this->default = $defaultValue;
        $this->hasDefault = true;

        return $this;
    }

    public function hasDefault(bool $hasDefault = true): self
    {
        $this->hasDefault = $hasDefault;

        return $this;
    }

    public function references(string $modelOrTable, string $field): self
    {
        $this->referenceModel = $modelOrTable;
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
            .(($this->nullable && !$this->autoIncrement) ? '->nullable()' : '->notNull()' )
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
            self::DATE => Param::date($nullable),
            self::DATETIME => Param::datetime($nullable),
            self::TIMESTAMP => Param::datetime($nullable),
            default => Param::string(true, $nullable),
        };
    }
}
