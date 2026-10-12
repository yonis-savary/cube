<?php

namespace Cube\Data\Database\Query;

use InvalidArgumentException;

class Identifier
{
    // A relation path joins names with `&` (`product&category`), a selected field alias adds `.column`
    private const NAME_PATTERN = '/^[A-Za-z0-9_$&.]+$/';
    private const COLUMN_PATTERN = '/^[A-Za-z_][A-Za-z0-9_$]*$/';

    public const OPERATORS = ['=', '<>', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];
    public const JOIN_TYPES = ['LEFT', 'RIGHT', 'INNER', 'CROSS', 'FULL', 'LEFT OUTER', 'RIGHT OUTER', 'FULL OUTER'];

    public static function isColumn(string $column): bool
    {
        return 1 === preg_match(self::COLUMN_PATTERN, $column);
    }

    public static function assertColumn(string $column): void
    {
        if (!self::isColumn($column))
            throw new InvalidArgumentException("Invalid SQL column name [{$column}], expected letters, digits and underscores");
    }

    public static function assertName(string $name): void
    {
        if (!preg_match(self::NAME_PATTERN, $name))
            throw new InvalidArgumentException("Invalid SQL table or alias name [{$name}], expected letters, digits, underscores, '&' and '.'");
    }

    public static function assertOperator(string $operator): void
    {
        if (!in_array(strtoupper(trim($operator)), self::OPERATORS, true))
            throw new InvalidArgumentException("Invalid SQL operator [{$operator}], expected one of ".join(', ', self::OPERATORS));
    }

    public static function assertJoinType(string $type): void
    {
        if (!in_array(strtoupper(trim($type)), self::JOIN_TYPES, true))
            throw new InvalidArgumentException("Invalid SQL join type [{$type}], expected one of ".join(', ', self::JOIN_TYPES));
    }
}
