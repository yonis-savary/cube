<?php

namespace Cube;

use Cube\Env\Environment;

if (!function_exists('env')) {
    /**
     * Return a value (by default from your `.env` file).
     *
     * **Please use it only inside your configuration file in case this one is cached**
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Environment::getInstance()->get($key, $default);
    }
}
