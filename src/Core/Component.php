<?php

namespace Cube\Core;

trait Component
{
    private static ?self $instance = null;

    public static function getDefaultInstance(): static
    {
        return Autoloader::instanciate(static::class);
    }

    public static function getInstance(): static
    {
        if (!self::hasInstance()) {
            self::$instance = self::getDefaultInstance();
        }

        return self::$instance;
    }

    /**
     * @var static
     *
     * @param mixed $instance
     */
    public static function setInstance($instance): void
    {
        self::$instance = $instance;
    }

    public static function hasInstance(): bool
    {
        return !is_null(self::$instance);
    }

    public static function removeInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @var static
     * @var callable Callback
     *
     * @param mixed $scopedInstance
     */
    public static function withInstance($scopedInstance, callable $callback): void
    {
        $oldInstance = self::getInstance();

        self::setInstance($scopedInstance);
        $callback($scopedInstance, $oldInstance);

        self::setInstance($oldInstance);
    }

    public function asGlobalInstance(callable $callback): void
    {
        self::withInstance($this, $callback);
    }
}
