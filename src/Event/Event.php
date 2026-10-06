<?php

namespace Cube\Event;

abstract class Event
{
    public function getName(): string
    {
        return static::class;
    }

    /** @param \Closure(static) $callback */
    public static function on(callable $callback, ?EventDispatcher $dispatcher = null): void
    {
        $dispatcher ??= Events::getInstance();
        $dispatcher->on(static::class, $callback);
    }

    public function dispatch(?EventDispatcher $dispatcher = null): self
    {
        $dispatcher ??= Events::getInstance();
        $dispatcher->dispatch($this);

        return $this;
    }
}
