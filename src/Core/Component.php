<?php

namespace Cube\Core;

trait Component
{
    /**
     * @var array<class-string,?static>
     */
    private static array $instances = [];

    public static function getDefaultInstance(): static
    {
        return static::class === Injector::class
            ? new static()
            : Injector::getInstance()->instanciate(static::class);
    }

    /**
     * @return static
     */
    public static function getInstance(): mixed
    {
        return static::$instances[static::class] ??= static::getDefaultInstance();
    }

    /**
     * @var static
     *
     * @param mixed $instance
     */
    public static function setInstance($instance): void
    {
        static::$instances[static::class] = $instance;
    }

    public static function hasInstance(): bool
    {
        return isset(static::$instances[static::class]);
    }

    public static function removeInstance(): void
    {
        unset(static::$instances[static::class]);
    }

    /**
     * @param \Closure(static,static):void $callback
     * @param mixed $scopedInstance
     */
    public static function withInstance($scopedInstance, callable $callback): void
    {
        // Read the slot instead of getInstance() : building a default one only to
        // restore it would leave a component instanciated that nobody asked for
        $oldInstance = static::$instances[static::class] ?? null;

        static::setInstance($scopedInstance);
        try {
            $callback($scopedInstance, $oldInstance);
        } finally {
            static::setInstance($oldInstance);
        }
    }

    /** @param \Closure(static,static):void $callback */
    public function asGlobalInstance(callable $callback): void
    {
        static::withInstance($this, $callback);
    }
}
