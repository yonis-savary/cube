<?php

namespace Cube;

use Cube\Env\Environment;

if (!function_exists('Cube\env')) {
    /**
     * Return a value (by default from your `.env` file).
     *
     * **Please use it only inside your configuration file in case this one is cached**
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Environment::getInstance()->get($key, $default);
    }

    function environmentName(): ?string {
        return env('env') ?? env('environment');
    }

    function isProduction(): bool {
        return str_starts_with(strtolower(environmentName() ?? 'debug'), 'prod');
    }

    function isDebug(): bool {
        return null !== environmentName() && !isProduction();
    }
}
