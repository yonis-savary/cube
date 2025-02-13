<?php

namespace YonisSavary\Cube\Env;

use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Env\Cache\Element;
use YonisSavary\Cube\Env\Storage;

class Cache
{
    const PERMANENT = 0;
    const SECOND = 1;
    const MINUTE = self::SECOND * 60;
    const HOUR   = self::MINUTE * 60;
    const DAY    = self::HOUR * 24;
    const WEEK   = self::DAY * 7;
    const MONTH  = self::DAY * 31;

    use Component;

    protected Storage $storage;
    /** @var array<string,Element> */
    protected array $index = [];

    public static function getDefaultInstance(): static
    {
        return new self(Storage::getInstance()->child("Cache"));
    }

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;

        foreach ($storage->files() as $file)
        {
            if (! $element = Element::fromFile($file))
                continue;

            $this->index[$element->key] = $element;
        }
    }

    public function get(string $key, mixed $default=null): mixed
    {
        if ($this->has($key))
            return $this->index[$key]->getValue();

        return $default;
    }

    public function &getReference(string $key, mixed $default=null, int $timeToLive=self::MONTH, ?int $creationDate=null): mixed
    {
        if (!array_key_exists($key, $this->index))
            $this->set($key, $default, $timeToLive, $creationDate);

        return $this->index[$key]->asReference();
    }

    public function set(string $key, mixed $value, int $timeToLive=self::MONTH, ?int $creationDate=null)
    {
        $creationDate ??= time();

        $this->delete($key);

        $element = new Element($key, $value, $timeToLive, $creationDate);
        $this->index[$key] = $element;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->index);
    }

    public function delete(string $key)
    {
        if ($this->has($key))
        {
            $this->index[$key]->destroy();
            unset($this->index[$key]);
        }
    }

    public function save()
    {
        foreach ($this->index as $element)
            $element->save($this->storage);
    }

    /**
     * Get another Cache instance made from a subdirectory inside the current instance directory
     */
    public function child(string $name): self
    {
        return new Cache($this->storage->child($name));
    }

    public function __destruct()
    {
        $this->save();
    }


    /**
     * Destroy every element of the cache
     */
    public function clear()
    {
        foreach ($this->index as $object)
            $object->destroy();

        $this->index = [];
    }

    /**
     * Alias of `Cache::clear()`
     */
    public function flush()
    {
        $this->clear();
    }

    public function getStorage(): Storage
    {
        return $this->storage;
    }
}