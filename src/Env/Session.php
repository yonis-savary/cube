<?php

namespace YonisSavary\Cube\Env;

use RuntimeException;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Env\Session\SessionConfiguration;

class Session
{
    use Component;

    protected ?string $namespace = null;

    public static function getDefaultInstance(): static
    {
        $config = SessionConfiguration::resolve();
        return new self($config->namespace);
    }

    public function __construct(?string $namespace=null)
    {
        $this->namespace = $namespace;

        $status = session_start();
        if ($status === PHP_SESSION_DISABLED)
            throw new RuntimeException("Cannot start session as PHP session are disabled");

        if ($status !== PHP_SESSION_ACTIVE)
        {
            session_name(md5(Autoloader::getProjectPath()));
            session_start();
        }
    }

    public function getNamespacedKey(string $key): string
    {
        if ($namespace = $this->namespace)
            return "$namespace$key";
        return $key;
    }

    public function set(string $key, mixed $value)
    {
        $key = $this->getNamespacedKey($key);
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default=null): mixed
    {
        $key = $this->getNamespacedKey($key);
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key)
    {
        $key = $this->getNamespacedKey($key);
        return array_key_exists($key, $_SESSION);
    }

    public function unset(string $key)
    {
        $key = $this->getNamespacedKey($key);
        unset($_SESSION[$key]);
    }
}