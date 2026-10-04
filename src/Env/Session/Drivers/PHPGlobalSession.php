<?php

namespace Cube\Env\Session\Drivers;

use Cube\Utils\Path;

class PHPGlobalSession implements SessionDriverInterface
{
    protected string $namespace;

    public function __construct(
        public readonly string $sameSite = 'Lax',
    )
    {}

    public function initialize(string $namespace) {
        $this->namespace = $namespace;

        $status = session_status();
        if (PHP_SESSION_DISABLED === $status) {
            throw new \RuntimeException('Cannot start session as PHP session are disabled');
        }

        if (PHP_SESSION_ACTIVE !== $status)
            $this->start();

        // Nested under one fixed key : PHP's session serializer drops numeric top-level keys, which a namespace like '0' would be
        $_SESSION['cube'][$namespace] ??= [];
    }

    protected function start(): void
    {
        $https = $_SERVER['HTTPS'] ?? '';

        session_name(md5(Path::getProjectPath()));
        session_set_cookie_params([
            'httponly' => true,
            'secure' => '' !== $https && 'off' !== strtolower($https),
            'samesite' => $this->sameSite,
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed {
        return array_key_exists($key, $_SESSION['cube'][$this->namespace])
            ? $_SESSION['cube'][$this->namespace][$key]
            : $default;
    }

    public function set(string $key, mixed $value) {
        $_SESSION['cube'][$this->namespace][$key] = $value;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $_SESSION['cube'][$this->namespace]);
    }

    public function delete(string $key): void {
        unset($_SESSION['cube'][$this->namespace][$key]);
    }

    public function clear(): void {
        $_SESSION['cube'][$this->namespace] = [];
    }

    public function regenerate() {
        if (headers_sent())
            return;

        session_regenerate_id(true);
    }
}