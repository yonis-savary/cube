<?php

namespace Cube\Core;

trait Component
{
    private static ?self $instance = null;

    /**
     * @return static
     */
    public static function getDefaultInstance(): static
    {
        return new self();
    }

    /**
     * @return static
     */
    public static function getInstance(): static
    {
        if (!self::hasInstance())
            self::$instance = self::getDefaultInstance();

        return self::$instance;
    }

    /**
     * @var static $instance
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
     * @var static $scopedInstance
     * @var callable $callback Callback
     */
    public static function withInstance($scopedInstance, callable $callback): void
    {
        $oldInstance = self::getInstance();

        self::setInstance($scopedInstance);
        $callback($scopedInstance, $oldInstance);

        self::setInstance($oldInstance);
    }
}