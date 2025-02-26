<?php

namespace Cube\Utils;

class Text
{
    public static function toFile(string $text, int $indentLevel = 2): string
    {
        return trim(preg_replace("/^(    |\t){".$indentLevel.'}/m', '', $text), "\n");
    }

    public static function endsWith(string $string, string $suffix): string
    {
        if (!str_ends_with($string, $suffix)) {
            return $string.$suffix;
        }

        return $string;
    }

    public static function dontEndsWith(string $string, string $suffix): string
    {
        $suffixLength = strlen($suffix);
        while (str_ends_with($string, $suffix)) {
            $string = substr($string, 0, strlen($string) - $suffixLength);
        }

        return $string;
    }

    public static function startsWith(string $string, string $prefix): string
    {
        if (!str_starts_with($string, $prefix)) {
            return $prefix.$string;
        }

        return $string;
    }

    public static function dontStartsWith(string $string, string $prefix): string
    {
        $prefixLength = strlen($prefix);
        while (str_starts_with($string, $prefix)) {
            $string = substr($string, $prefixLength);
        }

        return $string;
    }

    public static function anyToString(mixed $value): string
    {
        if (is_numeric($value)) {
            return "{$value}";
        }

        if (is_string($value)) {
            return $value;
        }

        return match ($value) {
            true => 'true',
            false => 'false',
            null => 'null',
            default => str_replace("\n", '', print_r($value, true)),
        };
    }

    public static function interpolate(null|string|\Stringable $message, array $context = []): string
    {
        $message ??= '';
        $message = (string) $message;

        foreach ($context as $key => $value) {
            $message = str_replace('{'.$key.'}', self::anyToString($value), $message);
        }

        return $message;
    }
}
