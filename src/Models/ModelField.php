<?php

namespace YonisSavary\Cube\Models;

use InvalidArgumentException;

class ModelField
{
    const STRING = "STRING";
    const INTEGER = "INTEGER";
    const FLOAT = "FLOAT";
    const BOOLEAN = "BOOLEAN";
    const DECIMAL = "DECIMAL";
    const DATE = "DATE";
    const DATETIME = "DATETIME";
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
    public mixed $default = null;

    public ?string $referenceTable = null ;
    public ?string $referenceField = null ;

    public bool $autoincrement = false;

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
        $this->autoincrement = true;
        return $this;
    }

    public function nullable(bool $nullable=true): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;
        return $this;
    }

    public function references(string $model, string $field): self
    {
        $this->referenceTable = $model;
        $this->referenceField = $field;
        return $this;
    }
}