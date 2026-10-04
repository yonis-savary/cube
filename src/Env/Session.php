<?php

namespace Cube\Env;

use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Env\Session\Drivers\SessionDriverInterface;
use Cube\Env\Session\SessionConfiguration;

class Session
{
    use Component;

    protected SessionDriverInterface $driver;

    public function __construct(SessionConfiguration $config, ?string $namespace = null)
    {
        $namespace ??= $config->namespace;
        if ('' === $namespace)
            throw new \InvalidArgumentException('Session namespace cannot be empty');

        $this->driver = clone $config->driver;
        $this->driver->initialize($namespace);
    }

    public function regenerateId(): void
    {
        $this->driver->regenerate();
    }

    public function set(string $key, mixed $value): void
    {
        $this->driver->set($key, $value);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function unset(string ...$keys): void
    {
        Bunch::of($keys)
            ->forEach(fn($key) => $this->driver->delete($key));
    }

    public function clear(): void
    {
        $this->driver->clear();
    }
}
