<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Env\Cache\CacheElement;

class Cache
{
    use Component;
    public const PERMANENT = 0;
    public const SECOND = 1;
    public const MINUTE = self::SECOND * 60;
    public const HOUR = self::MINUTE * 60;
    public const DAY = self::HOUR * 24;
    public const WEEK = self::DAY * 7;
    public const MONTH = self::DAY * 31;

    protected Storage $storage;

    /** @var array<string,CacheElement> */
    protected array $index = [];

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;

        foreach ($storage->files() as $file) {
            if (!$element = CacheElement::fromFile($file)) {
                continue;
            }

            $this->index[$element->key] = $element;
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    public static function getDefaultInstance(): static
    {
        return new self(Storage::getInstance()->child('Cache'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->index[$key]->getValue();
        }

        return $default;
    }

    public function try(string $key): mixed
    {
        return $this->get($key, false);
    }

    public function &getReference(string $key, mixed $default = null, int $timeToLive = self::MONTH, ?int $creationDate = null): mixed
    {
        if (!array_key_exists($key, $this->index)) {
            $this->set($key, $default, $timeToLive, $creationDate);
        }

        return $this->index[$key]->asReference();
    }

    public function set(string $key, mixed $value, int $timeToLive = self::MONTH, ?int $creationDate = null)
    {
        $creationDate ??= time();

        $this->delete($key);

        $element = new CacheElement($key, $value, $timeToLive, $creationDate);
        $this->index[$key] = $element;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->index);
    }

    public function delete(string $key): void
    {
        if (!$this->has($key))
            return;

        $this->index[$key]->destroy();
        unset($this->index[$key]);
    }

    public function save(): void
    {
        foreach ($this->index as $element) {
            $element->save($this->storage);
        }
    }

    /**
     * Get another Cache instance made from a subdirectory inside the current instance directory.
     */
    public function child(string $name): self
    {
        return new Cache($this->storage->child($name));
    }

    /**
     * Destroy every element of the cache.
     */
    public function clear(): void
    {
        foreach ($this->index as $object) {
            $object->destroy();
        }

        $this->index = [];
    }

    /**
     * Alias of `Cache::clear()`.
     */
    public function flush(): void
    {
        $this->clear();
    }

    public function getStorage(): Storage
    {
        return $this->storage;
    }

    /**
     * @template TReturn
     *
     * @param \Closure():TReturn $callback
     * @return TReturn
     */
    public function generated(string $key, callable|\Closure $callback, int $timeToLive = self::MONTH, ?int $creationDate = null)
    {
        if ($this->has($key))
            return $this->get($key);

        $value = ($callback)();
        $this->set($key, $value, $timeToLive, $creationDate);
        return $value;
    }
}
