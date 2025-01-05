<?php

namespace YonisSavary\Cube\Configuration;

use Exception;
use PHPUnit\Runner\FileDoesNotExistException;
use RuntimeException;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Utils\Path;

class Configuration
{
    use Component;

    /** @var array<string,GenericElement> */
    protected array $generics = [];

    /** @var array<string,ConfigurationElement> */
    protected array $classElements = [];

    public static function getDefaultInstance(): static
    {
        $instance = new self();

        $file = Path::relative("cube.php");
        if (is_file($file))
            $instance->loadFile($file);

        return $instance;
    }

    public function __construct(ConfigurationElement ...$elements)
    {
        foreach ($elements as $element)
            $this->add($element);
    }

    public function add(ConfigurationElement $element): self
    {
        if ($element instanceof GenericElement)
            $this->generics[$element->getName()] = $element;
        else
            $this->classElements[$element->getName()] = $element;

        return $this;
    }

    public function loadFile(string $fileToLoad)
    {
        if (!is_file($fileToLoad))
            throw new FileDoesNotExistException($fileToLoad);

        $return = include $fileToLoad;

        if (!is_array($return))
            throw new RuntimeException(sprintf("Returned value from configuration file must be an array, %s returned", gettype($return)));

        foreach ($return as $element)
        {
            if (! $element instanceof ConfigurationElement)
                throw new Exception(sprintf('Returned values from a configuration file must extends ConfigurationElement, found ' . gettype($element) . ' element'));

            $this->add($element);
        }
    }

    public function resolve(string $class, mixed $default=null): mixed
    {
        return $this->classElements[$class] ?? $default;
    }

    public function resolveGeneric(string $class, mixed $default=null): mixed
    {
        if ($existing = $this->generics[$class] ?? false)
            return $existing->getValue();

        return $default;
    }
}