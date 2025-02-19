<?php

namespace Cube\Configuration;

abstract class ConfigurationElement
{
    /**
     * @return static
     */
    public static function resolve(?Configuration $configuration=null, ?self $default=null)
    {
        $class = get_called_class();

        $configuration ??= Configuration::getInstance();
        return $configuration->resolve($class) ?? $default ?? new $class();
    }

    public function getName(): string
    {
        return get_called_class();
    }
}