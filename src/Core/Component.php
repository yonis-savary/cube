<?php

namespace YonisSavary\Cube\Core;

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

    public static function hasInstance(): bool
    {
        return !is_null(self::$instance);
    }

    public static function removeInstance(): void
    {
        self::$instance = null;
    }
}