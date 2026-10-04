<?php

namespace Cube\Env\Session\Drivers;

use InvalidArgumentException;

use function Cube\env;

/** Keeps the PHP session in Redis through phpredis' own session handler ! */
class RedisSession extends PHPGlobalSession
{
    protected string $host;
    protected int $port;

    public function __construct(?string $host = null, int $port = 6379, string $sameSite = 'Lax')
    {
        parent::__construct($sameSite);

        $host ??= env('SESSION_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env SESSION_REDIS_HOST)');

        $this->host = $host;
        $this->port = $port;
    }

    protected function start(): void
    {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', "tcp://{$this->host}:{$this->port}");

        parent::start();
    }
}
