<?php

namespace Cube\Env\Session\Drivers;

use Cube\Env\Session\SessionConfiguration;

interface SessionDriverInterface
{
    public function initialize(string $namespace);
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value);
    public function has(string $key): bool;
    public function delete(string $key): void;
    public function clear(): void;
    public function regenerate();
}