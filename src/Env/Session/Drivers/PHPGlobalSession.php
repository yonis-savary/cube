<?php

namespace Cube\Env\Session\Drivers;

use Cube\Env\Logger\Logger;
use Cube\Utils\Path;

class PHPGlobalSession implements SessionDriverInterface
{
    protected string $namespace;

    public function __construct(
        public readonly string $sameSite = 'Lax',
        public readonly ?bool $secureCookie = null,
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
        session_name(md5(Path::getProjectPath()));
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $this->isCookieSecure(),
            'samesite' => $this->sameSite,
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public function isCookieSecure(): bool
    {
        if (null !== $this->secureCookie)
            return $this->secureCookie;

        $https = strtolower($_SERVER['HTTPS'] ?? '');
        if ('' !== $https && 'off' !== $https)
            return true;

        return 'https' === strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
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
        if (headers_sent($file, $line)) {
            Logger::getInstance()->warning('Session id kept : output already started at {file}:{line}, a planted session id survives this login', ['file' => $file, 'line' => $line]);
            return;
        }

        session_regenerate_id(true);
    }
}