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

    /**
     * Only a development environment named as such shows errors to clients : a `staging` or a `preprod`
     * often faces real users
     */
    function isDebug(): bool {
        $debugEnvs = ['debug', 'dev', 'development', 'local', 'test', 'testing'];
        return in_array(strtolower(environmentName() ?? ''), $debugEnvs, true);
    }

    /**
     * The given key, or `APP_KEY`, checked long enough to be used as one
     */
    function appKey(#[\SensitiveParameter] ?string $key = null): string {
        $key ??= env('APP_KEY', false);
        if (!$key)
            throw new \RuntimeException('No app key given nor configured : set APP_KEY, `php do key:generate` makes one');

        if (strlen($key) < 32)
            throw new \RuntimeException('The app key holds '.strlen($key).' bytes, at least 32 are expected : `php do key:generate` makes one');

        return $key;
    }
}
