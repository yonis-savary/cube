<?php

namespace Cube\Env\Cache\LocalDiskCache;

use Cube\Data\Bunch;
use Cube\Env\Cache\CacheDriverInterface;
use Cube\Env\Storage;

class LocalDiskCache implements CacheDriverInterface
{
    public const INCREMENT_LOCK_FILE = '.increment-lock';

    protected Storage $storage;

    /** @var array<string,LocalDiskCacheElement> */
    protected array $index = [];

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? Storage::getInstance()->child('Cache');
    }

    public function initialize()
    {
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

    public function __serialize(): array
    {
        return ['storage' => $this->storage];
    }

    public function __unserialize(array $data): void
    {
        $this->storage = $data['storage'];
        $this->index = [];
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
        if (!array_key_exists($key, $this->index))
            return false;
        $element = $this->index[$key];
        if ($element->isExpired()) {
            $this->delete($key);
            return false;
        }

        if ($element->isLoaded() || $element->load())
            return true;
        unset($this->index[$key]);
        return false;
    }

    public function delete(string $key): void
    {
        if (!array_key_exists($key, $this->index))
            return;
        $this->index[$key]->destroy();
        unset($this->index[$key]);
    }

    public function increment(string $key, int $by = 1, int $timeToLive = self::MONTH): int
    {
        // The index is a snapshot taken at initialize() : a counter is read from and written to its file under a lock shared by every process
        $lock = fopen($this->storage->path(self::INCREMENT_LOCK_FILE), 'c');
        flock($lock, LOCK_EX);

        try {
            $element = $this->readFromDisk($key) ?? new LocalDiskCacheElement($key, 0, $timeToLive, time());
            $element->setValue((int) $element->getValue() + $by);
            $element->save($this->storage);
            $this->index[$key] = $element;

            return $element->getValue();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function readFromDisk(string $key): ?LocalDiskCacheElement
    {
        // Keys are written rawurlencoded, which leaves no glob character, but "*_*_key" also matches "*_*_other_key"
        return Bunch::wrap(glob($this->storage->path('*_*_'.rawurlencode($key))) ?: [])
            ->map(fn (string $file) => LocalDiskCacheElement::fromFile($file))
            ->first(fn (?LocalDiskCacheElement $element) => $element?->key === $key && $element->load())
        ;
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
    public function clear(string $keyPrefix = ''): array
    {
        $removed = [];

        foreach ($this->index as $key => $object) {
            if (!str_starts_with($key, $keyPrefix))
                continue;
            $removed[] = $object->getFile() ?? $object->key;
            $object->destroy();
            unset($this->index[$key]);
        }

        return $removed;
    }
}
