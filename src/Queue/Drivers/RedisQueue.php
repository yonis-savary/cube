<?php

namespace Cube\Queue\Drivers;

use Cube\Env\Logger\Logger;
use Cube\Utils\SignedPayload;
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

    public function __construct(
        ?string $host=null,
        int $port=6379,
        #[\SensitiveParameter] ?string $password=null
    ) {
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

    protected function tryToReconnect(): void
    {
        try {
            $this->reconnect();
        } catch (RedisException|RuntimeException $exception) {
            Logger::getInstance()->warning('Could not reconnect queue [{queue}] to redis : {message}', [
                'queue' => $this->identifier,
                'message' => $exception->getMessage(),
            ]);
            sleep(self::BLOCKING_TIMEOUT);
        }
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
        $this->connection->rPush($this->identifier, SignedPayload::sign(serialize($args)));
    }

    public function next(): ?array
    {
        try
        {
            $result = $this->connection->blPop($this->identifier, self::BLOCKING_TIMEOUT);
        }
        catch (RedisException) {
            $this->tryToReconnect();

            return null;
        }

        if (!$result)
            return null;

        if (null === $payload = SignedPayload::verify($result[1])) {
            Logger::getInstance()->warning('Dropped a job with an invalid signature from queue [{queue}]', ['queue' => $this->identifier]);
            return null;
        }

        $element = unserialize($payload);

        return is_array($element) ? $element : null;
    }
}
