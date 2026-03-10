<?php

namespace Cube\Env\Cache\LocalDiskCache;

use Cube\Env\Cache\CacheDriverInterface;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCacheElement;
use Cube\Env\Storage;

class LocalDiskCache implements CacheDriverInterface
{
    protected Storage $storage;

    /** @var array<string,LocalDiskCacheElement> */
    protected array $index = [];

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? Storage::getInstance()->child('Cache');
    }

    public function initialize() {
        $storage = $this->storage;
        foreach ($storage->files() as $file) {
            if (!$element = LocalDiskCacheElement::fromFile($file)) {
                continue;
            }

            $this->index[$element->key] = $element;
        }
    }

    public function __destruct()
    {
        $this->save();
    }


    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->index[$key]->getValue();
        }

        return $default;
    }

    public function &getReference(string $key): mixed
    {
        return $this->index[$key]->asReference();
    }

    public function set(string $key, mixed $value, int $timeToLive = self::MONTH, ?int $creationDate = null)
    {
        $creationDate ??= time();

        $this->delete($key);

        $element = new LocalDiskCacheElement($key, $value, $timeToLive, $creationDate);
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
     * Destroy every element of the cache.
     */
    public function clear(): void
    {
        foreach ($this->index as $object) {
            $object->destroy();
        }

        $this->index = [];
    }
}