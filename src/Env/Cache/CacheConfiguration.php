<?php

namespace Cube\Env\Cache;

use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Configuration\ConfigurationElement;

class CacheConfiguration extends ConfigurationElement
{
    public CacheDriverInterface $driver;

    public function __construct(?CacheDriverInterface $driver = null)
    {
        $this->driver = $driver ?? new LocalDiskCache();
    }
}