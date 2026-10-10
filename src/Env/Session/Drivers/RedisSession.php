<?php

namespace Cube\Env\Session\Drivers;

use InvalidArgumentException;

use function Cube\env;

/** Keeps the PHP session in Redis through phpredis' own session handler ! */
class RedisSession extends PHPGlobalSession
{
    protected string $host;
    protected int $port;
    protected ?string $password;

    public function __construct(?string $host = null, int $port = 6379, string $sameSite = 'Lax', ?bool $secureCookie = null, ?string $password = null)
    {
        parent::__construct($sameSite, $secureCookie);

        $host ??= env('SESSION_REDIS_HOST', 'redis');
        if (!$host)
            throw new InvalidArgumentException('$host parameter is needed (can also be configured through env SESSION_REDIS_HOST)');

        $this->host = $host;
        $this->port = $port;
        $this->password = $password ?? (env('SESSION_REDIS_PASSWORD') ?: null);
    }

    protected function start(): void
    {
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', $this->getSavePath());

        parent::start();
    }

    public function getSavePath(): string
    {
        $path = "tcp://{$this->host}:{$this->port}";

        return $this->password
            ? $path.'?auth='.rawurlencode($this->password)
            : $path;
    }
}
