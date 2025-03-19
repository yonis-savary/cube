<?php

namespace Cube\Env;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Env\Session\SessionConfiguration;

class Session
{
    use Component;

    protected ?string $namespace = null;

    public function __construct(?string $namespace = null)
    {
        if (!$namespace) {
            throw new \InvalidArgumentException("Invalid session namespace [{$namespace}], namespace cannot be empty");
        }

        $this->namespace = $namespace;

        session_start();
        $status = session_status();
        if (PHP_SESSION_DISABLED === $status) {
            throw new \RuntimeException('Cannot start session as PHP session are disabled');
        }

        if (PHP_SESSION_ACTIVE !== $status) {
            session_name(md5(Autoloader::getProjectPath()));
            session_start();
        }
    }

    public static function getDefaultInstance(): static
    {
        $config = SessionConfiguration::resolve();

        return new self($config->namespace);
    }

    public function getNamespacedKey(string $key): string
    {
        if ($namespace = $this->namespace) {
            return "{$namespace}{$key}";
        }

        return $key;
    }

    public function set(string $key, mixed $value)
    {
        $key = $this->getNamespacedKey($key);
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
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
