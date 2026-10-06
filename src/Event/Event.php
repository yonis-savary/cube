<?php

namespace Cube\Event;

abstract class Event
{
    protected bool $prevented = false;

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

    /** @return bool Was the event dispatched successfully ? */
    public function dispatch(?EventDispatcher $dispatcher = null): bool
    {
        $dispatcher ??= Events::getInstance();
        return $dispatcher->dispatch($this);
    }

    public function prevent(): static {
        $this->prevented = true;
        return $this;
    }

    public function isPrevented(): bool {
        return $this->prevented;
    }
}
