<?php

namespace YonisSavary\Cube\Configuration;

use Exception;
use InvalidArgumentException;
use PHPUnit\Runner\FileDoesNotExistException;
use RuntimeException;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Env\Cache;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Utils\Path;

class Configuration
{
    use Component;

    /** @var array<string,GenericElement> */
    protected array $generics = [];

    /** @var array<string,ConfigurationElement> */
    protected array $classElements = [];

    protected ?string $identifier = null;

    public static function getDefaultInstance(): static
    {
        $instance = (new self())->identify("cube-default");

        if ($instance->loadFromCache())
        {
            $file = Path::relative("cube.php");
            if (is_file($file))
                $instance->loadFile($file);
        }

        return $instance;
    }

    public static function getDefaultConfigurationCache(): Cache
    {
        return Cache::getInstance()->child("Configurations");
    }

    public function identify(string $newIdentifier): self
    {
        $this->identifier = $newIdentifier;
        return $this;
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

    public function loadFromCache(?Cache $cache=null): bool
    {
        if (!$this->identifier)
            throw new InvalidArgumentException("Please use Configuration->identify() before loading it from cache");

        $cache ??= self::getDefaultConfigurationCache();

        if (!$cache->has($this->identifier))
            return false;

        list($this->generics, $this->classElements) = $cache->get($this->identifier, []);
        Logger::getInstance()->debug("Successfuly got config from cache !");
        return true;
    }

    public function putToCache(?Cache $cache=null): Storage
    {
        if (!$this->identifier)
            throw new InvalidArgumentException("Please use Configuration->identify() before putting it to cache");

        $cache ??= self::getDefaultConfigurationCache();

        $cache->set($this->identifier, serialize([$this->generics, $this->classElements]), Cache::PERMANENT);
        return $cache->getStorage();
    }
}