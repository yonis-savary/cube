<?php

namespace Cube\Database\Query;

class QueryBase
{
    public const INSERT = 'insert';
    public const SELECT = 'select';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    public const ALLOWED_TYPES = [
        self::INSERT,
        self::SELECT,
        self::UPDATE,
        self::DELETE,
    ];

    public readonly string $table;
    public readonly string $type;
    public string $model;

    public function __construct(string $type, string $table, string $model)
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException('$type must be in '.join(',', self::ALLOWED_TYPES));
        }

        $this->type = $type;
        $this->table = $table;
        $this->model = $model;
    }
}
