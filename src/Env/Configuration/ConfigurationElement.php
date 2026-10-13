<?php

namespace Cube\Env\Configuration;

use Cube\Env\Configuration;
use ReflectionClass;
use RuntimeException;

abstract class ConfigurationElement
{
    /**
     * @return static
     */
    public static function resolve(?Configuration $configuration = null, ?self $default = null)
    {
        $class = static::class;

        $configuration ??= Configuration::getInstance();

        return $configuration->resolve($class) ?? $default ?? static::fromDefaults();
    }

    protected static function fromDefaults(): static
    {
        $class = static::class;

        if ((new ReflectionClass($class))->getConstructor()?->getNumberOfRequiredParameters())
            throw new RuntimeException("{$class} has required options and no default : declare it in your cube.php");

        return new $class();
    }

    public function getName(): string
    {
        return static::class;
    }
}
