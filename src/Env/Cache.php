<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\CacheDriverInterface;

class Cache
{
    use Component;

    public const PERMANENT = CacheDriverInterface::PERMANENT;
    public const SECOND = CacheDriverInterface::SECOND;
    public const MINUTE = CacheDriverInterface::MINUTE;
    public const HOUR = CacheDriverInterface::HOUR;
    public const DAY = CacheDriverInterface::DAY;
    public const WEEK = CacheDriverInterface::WEEK;
    public const MONTH = CacheDriverInterface::MONTH;

    protected CacheDriverInterface $driver;

    public function __construct(CacheConfiguration $configuration)
    {
        $this->driver = $configuration->driver;
        $this->driver->initialize();
    }

    public function get(string $key, mixed $default=null): mixed {
        return $this->driver->get($key) ?? $default;
    }

    public function &getReference(string $key, mixed $default): mixed {
        if (!$this->has($key))
            $this->set($key, $default);

        return $this->driver->getReference($key);
    }

    public function try(string $key): mixed {
        return $this->get($key, false);
    }

    /**
     * @param mixed $value Can be any value (shall be serialized), can be a callback (then its return value is registered)
     * @return mixed set value
     */
    public function getOrSet(string $key, mixed $value, mixed $timeToLive = self::MONTH, ?int $creationDate = null): mixed {
        if ($this->has($key))
            return $this->get($key);

        return $this->set($key, $value, $timeToLive, $creationDate);
    }

    /**
     * @param mixed $value Can be any value (shall be serialized), can be a callback (then its return value is registered)
     * @return mixed set value
     */
    public function set(string $key, mixed $value, int $timeToLive = self::MONTH, ?int $creationDate = null): mixed {
        if (is_callable($value))
            $value = ($value)();

        $this->driver->set($key, $value, $timeToLive, $creationDate);
        return $value;
    }

    public function has(string $key): bool {
        return $this->driver->has($key);
    }

    public function delete(string $key): void {
        $this->driver->delete($key);
    }

    public function clear(): void {
        $this->driver->clear();
    }
}
