<?php

namespace YonisSavary\Cube\Utils;

/**
 * This class is used to hold method for type with reserved keyword such as "Array"
 */
class Utils
{
    public static function isAssoc(array $array, bool $treatEmptyAsList=false): bool
    {
        return !self::isList($array, $treatEmptyAsList);
    }

    public static function isList(array $array, bool $treatEmptyAsList=true): bool
    {
        if (empty($array))
            return $treatEmptyAsList;

        return array_is_list($array);
    }
}