<?php

namespace Cube\Queue\Drivers;

use InvalidArgumentException;
use Redis;
use RedisException;
use RuntimeException;

use function Cube\env;

class RedisQueue extends BasicQueueDriver
{
    protected const BLOCKING_TIMEOUT = 1;

    protected Redis $connection;
    protected string $host;
    protected int $port;
    protected ?string $password;

    public function __construct(?string $host=null, int $port=6379, ?string $password=null)
    {
        $host ??= env('QUEUE_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env QUEUE_REDIS_HOST)');

        $this->host = $host;
        $this->port = $port;
        $this->password = $password ?? (env('QUEUE_REDIS_PASSWORD') ?: null);
        $this->connection = new Redis();
        $this->reconnect();
    }

    protected function reconnect(): void
    {
        $host = $this->host;
        $port = $this->port;

        if (!$this->connection->connect($host, $port))
            throw new RuntimeException("Could not connect to redis service $host:$port");

        if ($this->password && !$this->authenticate())
            throw new RuntimeException("Redis service $host:$port refused the password");
    }

    protected function authenticate(): bool
    {
        try {
            return $this->connection->auth($this->password);
        } catch (RedisException) {
            return false;
        }
    }

    public function flush(): void
    {
        $this->connection->del($this->identifier);
    }

    public function push(array $args): void
    {
        $this->connection->rPush($this->identifier, serialize($args));
    }

    public function next(): ?array
    {
        try
        {
            $result = $this->connection->blPop($this->identifier, self::BLOCKING_TIMEOUT);
        }
        catch (RedisException $_) {
            $this->reconnect();

            return null;
        }

        return $result
            ? unserialize($result[1])
            : null;
    }
}
