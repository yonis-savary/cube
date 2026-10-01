<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Env\Session\SessionConfiguration;
use Cube\Utils\Path;

class Session
{
    use Component;

    protected string $namespace;

    public function __construct(?string $namespace = null)
    {
        if (null === $namespace || '' === $namespace) {
            throw new \InvalidArgumentException('Session namespace cannot be empty');
        }

        $this->namespace = $namespace;

        $status = session_status();
        if (PHP_SESSION_DISABLED === $status) {
            throw new \RuntimeException('Cannot start session as PHP session are disabled');
        }

        if (PHP_SESSION_ACTIVE !== $status) {
            $this->start();
        }
    }

    protected function start(): void
    {
        $https = $_SERVER['HTTPS'] ?? '';

        session_name(md5(Path::getProjectPath()));
        session_set_cookie_params([
            'httponly' => true,
            'secure' => '' !== $https && 'off' !== strtolower($https),
            'samesite' => SessionConfiguration::resolve()->sameSite,
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public function regenerateId(): void
    {
        // Impossible once output started, which only happens outside of an HTTP request
        if (headers_sent())
            return;

        session_regenerate_id(true);
    }

    public static function getDefaultInstance(): static
    {
        $config = SessionConfiguration::resolve();

        return new self($config->namespace);
    }

    public function getNamespacedKey(string $key): string
    {
        return "{$this->namespace}{$key}";
    }

    public function set(string $key, mixed $value): void
    {
        $key = $this->getNamespacedKey($key);
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->getNamespacedKey($key);

        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $key = $this->getNamespacedKey($key);

        return array_key_exists($key, $_SESSION);
    }

    public function unset(string ...$keys): void
    {
        Bunch::of($keys)
            ->map(fn($key) => $this->getNamespacedKey($key))
            ->forEach(function($key) { unset($_SESSION[$key]); });
    }
}
