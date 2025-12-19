<?php

namespace Cube\Queue\Drivers;

use Cube\Queue\QueueCallback;
use InvalidArgumentException;
use Redis;
use RuntimeException;

use function Cube\env;

class RedisQueue implements QueueDriver
{
    protected string $identifier;
    protected Redis $connection;

    public function __construct(string $identifier, ?string $host=null, int $port=6379)
    {
        $identifier = preg_replace('/[^a-z0-9]/i', '.', strtolower($identifier));
        $this->identifier = "queue:$identifier";

        $host = env('QUEUE_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env QUEUE_REDIS_HOST)');

        $this->connection = new Redis();
        if (!$this->connection->connect($host, $port))
            throw new RuntimeException("Could not connect to redis service $host:$port");
    }

    public function next(): QueueCallback {
        list($key, $element) = $this->connection->blPop($this->identifier, 0);
        return unserialize($element);
    }

    public function flush() {
        $this->connection->del($this->identifier);
    }

    public function push(QueueCallback $callback) {
        $this->connection->rPush($this->identifier, serialize($callback));
    }
}