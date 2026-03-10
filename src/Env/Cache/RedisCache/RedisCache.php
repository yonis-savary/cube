<?php

namespace Cube\Env\Cache\RedisCache;

use Cube\Env\Cache\CacheDriverInterface;
use Cube\Env\Logger\Logger;
use InvalidArgumentException;
use Redis;
use RuntimeException;

use function Cube\env;

class RedisCache implements CacheDriverInterface
{
    protected string $identifier;
    protected Redis $connection;

    protected string $host;
    protected int $port;

    public function __construct(string $identifier="cube", ?string $host=null, int $port=6379)
    {
        $identifier = preg_replace('/[^a-z0-9]/i', '.', strtolower($identifier));
        $this->identifier = "cache:$identifier:";

        $host ??= env('CACHE_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env CACHE_REDIS_HOST)');

        $this->host = $host;
        $this->port = $port;
    }

    public function initialize() {
        $this->connection = new Redis();
        if (!$this->connection->connect($this->host, $this->port))
            throw new RuntimeException("[Cache] Could not connect to redis service $this->host:$this->port");

        $this->connection->setOption(Redis::OPT_PREFIX, $this->identifier);
    }

    public function get(string $key): mixed {
        return $this->has($key) 
            ? unserialize($this->connection->get($key))
            : null;
    }

    public function &getReference(string $key): mixed
    {
        Logger::getInstance()->warning("Redis Cache does not support getReference() method, returned value won't be reactive");
        $value = $this->get($key);
        return $value;
    }

    public function set(string $key, mixed $value, int $timeToLive = self::MONTH, ?int $creationDate = null) {
        $value = serialize($value);
        if ($timeToLive === self::PERMANENT)
            $this->connection->set($key, $value);
        else
            $this->connection->setex($key, $timeToLive, $value);
    }

    public function has(string $key): bool {
        return $this->connection->exists($key) > 0;
    }

    public function delete(string $key): void {
        $this->connection->del($key);
    }

    public function clear(): void {
        $it = null;
        do {
            if ($keys = $this->connection->scan($it, $this->identifier . "*")) {
                foreach ($keys as $key) {
                    $key = substr($key, strlen($this->identifier));
                    $this->connection->del($key);
                }
            }
        } while ($it !== 0);
    }
}