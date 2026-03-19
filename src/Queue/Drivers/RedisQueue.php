<?php

namespace Cube\Queue\Drivers;

use Cube\Queue\QueueCallback;
use InvalidArgumentException;
use Redis;
use RedisException;
use RuntimeException;

use function Cube\env;

class RedisQueue extends BasicQueueDriver
{
    protected Redis $connection;
    protected string $host;
    protected string $port;

    public function __construct(?string $host=null, int $port=6379)
    {
        $host = env('QUEUE_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env QUEUE_REDIS_HOST)');

        $this->host = $host;
        $this->port = $port;
        $this->connection = new Redis();
    }

    protected function reconnect() {
        $host = $this->host;
        $port = $this->port;

        if (!$this->connection->connect($host, $port))
            throw new RuntimeException("Could not connect to redis service $host:$port");
    }

    public function flush() {
        $this->connection->del($this->identifier);
    }

    public function push(array $args) {
        $this->connection->rPush($this->identifier, serialize($args));
    }

    public function next(): array {
        while (true) {
            try
            {
                $result = $this->connection->blPop($this->identifier, 0);
                if ($result)
                    return unserialize($result[1]);
            }
            catch (RedisException $_) {
                $this->reconnect();
            }
        }

    }
}