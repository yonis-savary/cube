<?php

namespace Cube\Configuration;

abstract class ConfigurationElement
{
    /**
     * @return static
     */
    public static function resolve(?Configuration $configuration = null, ?self $default = null)
    {
        $class = static::class;

        $configuration ??= Configuration::getInstance();

        return $configuration->resolve($class) ?? $default ?? new $class();
    }

    public function getName(): string
    {
        return static::class;
    }
}
