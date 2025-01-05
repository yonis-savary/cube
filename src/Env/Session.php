<?php

namespace YonisSavary\Cube\Env;

use InvalidArgumentException;
use RuntimeException;
use YonisSavary\Cube\Core\Component;

class Session
{
    use Component;

    public static function getDefaultInstance(): static
    {
        $config = SessionConfiguration::resolve();
        return new self($config->name);
    }

    public function __construct(string $name)
    {
        $status = session_start();
        if ($status === PHP_SESSION_DISABLED)
            throw new RuntimeException("Cannot start session as PHP session are disabled");

        if ($status !== PHP_SESSION_ACTIVE)
        {
            session_name($name);
            session_start();
        }
    }

    public function set(string $key, mixed $value)
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default=null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key)
    {
        return array_key_exists($key, $_SESSION);
    }

    public function unset(string $key)
    {
        unset($_SESSION[$key]);
    }
}