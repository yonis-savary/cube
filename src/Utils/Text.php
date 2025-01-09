<?php

namespace YonisSavary\Cube\Utils;

class Text
{
    public static function endsWith(string $string, string $suffix): string
    {
        if (!str_ends_with($string, $suffix))
            return $string . $suffix;

        return $string;
    }

    public static function dontEndsWith(string $string, string $suffix): string
    {
        $suffixLength = strlen($suffix);
        while (str_ends_with($string, $suffix))
            $string = substr($string, 0, strlen($string) - $suffixLength);

        return $string;
    }

    public static function startsWith(string $string, string $prefix): string
    {
        if (!str_starts_with($string, $prefix))
            return $prefix . $string;

        return $string;
    }

    public static function dontStartsWith(string $string, string $prefix): string
    {
        $prefixLength = strlen($prefix);
        while (str_starts_with($string, $prefix))
            $string = substr($string, $prefixLength);

        return $string;
    }

}