<?php

namespace Cube\Data\DataToObject\Generator;

use Cube\Data\Bunch;

class DtoNames
{
    /** What a name given on the command line must look like : a class name starting with an uppercase letter */
    public const CLASS_NAME_FORMAT = '/^[A-Z][a-zA-Z0-9]+$/';

    protected const RESERVED_CLASS_NAMES = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone',
        'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare',
        'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false',
        'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements',
        'include', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match',
        'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private',
        'protected', 'public', 'readonly', 'require', 'return', 'self', 'static', 'string', 'switch',
        'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    public static function className(string $name): string
    {
        $className = Bunch::wrap(self::words($name))->map(ucfirst(...))->join('');

        if (in_array(strtolower($className), self::RESERVED_CLASS_NAMES, true))
            return $className.'Dto';

        return self::withValidFirstCharacter($className);
    }

    public static function propertyName(string $key): string
    {
        $words = self::words($key);
        $leadingUnderscores = preg_replace('/^(_*).*$/s', '$1', $key);
        $propertyName =
            $leadingUnderscores .
            $words[0] .
            Bunch::wrap(array_slice($words, 1))->map(ucfirst(...))->join('');

        if ('this' === $propertyName)
            return 'thisValue';

        return self::withValidFirstCharacter($propertyName);
    }

    /**
     * `$name`, or `$name` followed by the first number that frees it (`url`, `url1`, `url2`...), compared
     * case-insensitively as PHP compares method and class names
     *
     * @param string[] $taken
     */
    public static function unique(string $name, array $taken): string
    {
        $taken = array_map(strtolower(...), $taken);

        $candidate = $name;
        for ($suffix = 1; in_array(strtolower($candidate), $taken, true); ++$suffix)
            $candidate = $name.$suffix;

        return $candidate;
    }

    /**
     * @return list<string>
     */
    protected static function words(string $name): array
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $name, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if (!count($words))
            throw new \InvalidArgumentException("Cannot derive a PHP name from [{$name}] : it holds no letter nor digit");

        return $words;
    }

    protected static function withValidFirstCharacter(string $name): string
    {
        return ctype_digit($name[0]) ? '_'.$name : $name;
    }
}
