<?php

namespace Cube\Env\Cache\RedisCache;

use Cube\Env\Cache\CacheDriverInterface;
use Cube\Env\Logger\Logger;
use Cube\Utils\SignedPayload;
use InvalidArgumentException;
use Redis;
use RedisException;
use RuntimeException;

use function Cube\env;

class RedisCache implements CacheDriverInterface
{
    protected string $identifier;
    protected Redis $connection;

    protected string $host;
    protected int $port;
    protected ?string $password;

    public function __construct(string $identifier="cube", ?string $host=null, int $port=6379, ?string $password=null)
    {
        $identifier = preg_replace('/[^a-z0-9]/i', '.', strtolower($identifier));
        $this->identifier = "cache:$identifier:";

        $host ??= env('CACHE_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env CACHE_REDIS_HOST)');

        $this->host = $host;
        $this->port = $port;
        $this->password = $password ?? (env('CACHE_REDIS_PASSWORD') ?: null);
    }

    public function initialize() {
        $this->connection = new Redis();
        if (!$this->connection->connect($this->host, $this->port))
            throw new RuntimeException("[Cache] Could not connect to redis service $this->host:$this->port");

        if ($this->password && !$this->authenticate())
            throw new RuntimeException("[Cache] Redis service $this->host:$this->port refused the password");

        $this->connection->setOption(Redis::OPT_PREFIX, $this->identifier);
    }

    protected function authenticate(): bool
    {
        try {
            return $this->connection->auth($this->password);
        } catch (RedisException) {
            return false;
        }
    }

    public function get(string $key): mixed {
        if (!$this->has($key))
            return null;

        if (null === $payload = SignedPayload::verify($this->connection->get($key))) {
            Logger::getInstance()->warning('Redis cache entry [{key}] has an invalid signature, it is ignored', ['key' => $key]);
            return null;
        }

        return unserialize($payload);
    }

    public function &getReference(string $key): mixed
    {
        Logger::getInstance()->warning("Redis Cache does not support getReference() method, returned value won't be reactive");
        $value = $this->get($key);
        return $value;
    }

    public function set(string $key, mixed $value, int $timeToLive = self::MONTH, ?int $creationDate = null) {
        $value = SignedPayload::sign(serialize($value));
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