<?php

namespace Cube\Data;

use Cube\Env\Storage;
use Cube\Utils\Utils;
use stdClass;

use function Cube\debug;

abstract class AutoDataToObject
{
    const TREAT_AS_ARRAY = "array";
    const TREAT_AS_OBJECT = "object";
    const TREAT_AS_ANY = "any";

    /**
     * When encoding to JSON, we need to know how to treat empty PHP Arrays
     * (Are they objects or true arrays)
     * this method shall return keys that should be treated as objects when encoded
     *
     * @return string[] keys to treat as objects
     */
    protected function objectKeys(): array
    {
        return [];
    }

    /**
     * When encoding to JSON, we need to know how to treat empty PHP Arrays
     * (Are they objects or true arrays)
     * this method shall return keys that should be treated as array when encoded
     *
     * @return string[] keys to treat as array
     */
    protected function arrayKeys(): array
    {
        return [];
    }

    /**
     * Some keys must be skipped if they don't contains any data,
     * this method return the list of these keys for the current class
     */
    protected function skipOnEmpty(): array
    {
        return [];
    }

    protected function convertValue(mixed $anyValue, string $convertionType=self::TREAT_AS_ANY): mixed
    {
        if ($anyValue instanceof AutoDataToObject) {
            $result = $anyValue->toArray();
            if (count($result))
                return $result;

            $anyValue = $result;
        }

        if (is_array($anyValue))
        {
            if (empty($anyValue))
            {
                return match($convertionType) {
                    self::TREAT_AS_ARRAY => (array) $anyValue,
                    self::TREAT_AS_OBJECT => (object) $anyValue,
                    default => $anyValue,
                };
            }
            else if (Utils::isList($anyValue)) {
                foreach ($anyValue as &$subValue) {
                    $subValue = $this->convertValue($subValue);
                }
            }
        }

        return $anyValue;
    }

    public function toArray(): array {
        $objectKeys = $this->objectKeys();
        $arrayKeys = $this->arrayKeys();
        $array = (array) $this;

        foreach ($array as $key => $value) {
            $convertionType = in_array($key, $objectKeys)
                ? self::TREAT_AS_OBJECT
                : ( in_array($key, $arrayKeys)
                    ? self::TREAT_AS_ARRAY
                    : self::TREAT_AS_ANY
                );

            $array[$key] = $this->convertValue($value, $convertionType);
        }

        foreach ($this->skipOnEmpty() as $skipKey) {
            if (!array_key_exists($skipKey, $array))
                continue;

            $value = $array[$skipKey];

            if (!($value instanceof stdClass || is_array($value)))
                continue;

            if (!count((array) $array[$skipKey]))
                unset($array[$skipKey]);
        }

        return $array;
    }

    public function toJSON(int $jsonEncodeFlags= JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR): string {
        return json_encode($this->toArray(), $jsonEncodeFlags);
    }
}