<?php

namespace Cube\Core;

trait Component
{
    private static ?self $instance = null;

    public static function getDefaultInstance(): static
    {
        return Injector::instanciate(static::class);
    }

    /**
     * @return static
     */
    public static function getInstance(): mixed
    {
        if (!static::hasInstance()) {
            static::$instance = static::getDefaultInstance();
        }

        return static::$instance;
    }

    /**
     * @var static
     *
     * @param mixed $instance
     */
    public static function setInstance($instance): void
    {
        static::$instance = $instance;
    }

    public static function hasInstance(): bool
    {
        return !is_null(static::$instance);
    }

    public static function removeInstance(): void
    {
        static::$instance = null;
    }

    /**
     * @var static
     * @var callable Callback
     *
     * @param mixed $scopedInstance
     */
    public static function withInstance($scopedInstance, callable $callback): void
    {
        $oldInstance = static::getInstance();

        static::setInstance($scopedInstance);
        $callback($scopedInstance, $oldInstance);

        static::setInstance($oldInstance);
    }

    public function asGlobalInstance(callable $callback): void
    {
        static::withInstance($this, $callback);
    }
}
