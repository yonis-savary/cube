<?php

namespace Cube\Configuration;

use Cube\Core\Component;
use Cube\Env\Cache;
use Cube\Env\Storage;
use Cube\Utils\Path;

class Configuration
{
    use Component;

    /** @var array<string,GenericElement> */
    protected array $generics = [];

    /** @var array<string,ConfigurationElement> */
    protected array $classElements = [];

    protected ?string $identifier = null;

    public function __construct(ConfigurationElement ...$elements)
    {
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public static function getDefaultInstance(): static
    {
        $instance = (new self())->identify('cube-default');

        if (!$instance->loadFromCache()) {
            $file = Path::relative('cube.php');
            if (is_file($file)) {
                $instance->loadFile($file);
            }
        }

        return $instance;
    }

    public static function getDefaultConfigurationCache(): Cache
    {
        return Cache::getInstance()->child('Configurations');
    }

    public function identify(string $newIdentifier): self
    {
        $this->identifier = $newIdentifier;

        return $this;
    }

    public function addFromImport(Import $element): self
    {
        foreach ($element->getElements() as $sub) {
            $this->add($sub);
        }

        return $this;
    }

    public function add(ConfigurationElement $element): self
    {
        if ($element instanceof GenericElement) {
            $this->generics[$element->getName()] = $element;
        } elseif ($element instanceof Import) {
            $this->addFromImport($element);
        } else {
            $this->classElements[$element->getName()] = $element;
        }

        return $this;
    }

    public function loadFile(string $fileToLoad)
    {
        if (!is_file($fileToLoad)) {
            throw new \RuntimeException($fileToLoad);
        }

        $return = include $fileToLoad;

        if (!is_array($return)) {
            throw new \RuntimeException(sprintf('Returned value from configuration file must be an array, %s returned', gettype($return)));
        }

        foreach ($return as $element) {
            if (!$element instanceof ConfigurationElement) {
                throw new \Exception(sprintf('Returned values from a configuration file must extends ConfigurationElement, found '.gettype($element).' element'));
            }

            $this->add($element);
        }
    }

    /**
     * @template TClass of ConfigurationElement
     *
     * @param class-string<TClass> $class
     *
     * @return TClass
     */
    public function resolve(string $class, mixed $default = null): mixed
    {
        return $this->classElements[$class] ?? $default;
    }

    public function resolveGeneric(string $class, mixed $default = null): mixed
    {
        if ($existing = $this->generics[$class] ?? false) {
            return $existing->getValue();
        }

        return $default;
    }

    public function loadFromCache(?Cache $cache = null): bool
    {
        if (!$this->identifier) {
            throw new \InvalidArgumentException('Please use Configuration->identify() before loading it from cache');
        }

        $cache ??= self::getDefaultConfigurationCache();

        if (!$cache->has($this->identifier)) {
            return false;
        }

        list($this->generics, $this->classElements) = $cache->get($this->identifier, []);

        return true;
    }

    public function putToCache(?Cache $cache = null): Storage
    {
        if (!$this->identifier) {
            throw new \InvalidArgumentException('Please use Configuration->identify() before putting it to cache');
        }

        $cache ??= self::getDefaultConfigurationCache();

        $cache->set($this->identifier, [$this->generics, $this->classElements], Cache::PERMANENT);

        return $cache->getStorage();
    }
}
