<?php

namespace Cube\Event;

abstract class Event
{
    public function getName(): string
    {
        return get_called_class();
    }

    public function dispatch(?EventDispatcher $dispatcher = null): self
    {
        $dispatcher ??= Events::getInstance();
        $dispatcher->dispatch($this);

        return $this;
    }

    public static function onTrigger(callable $callback, ?EventDispatcher $dispatcher = null): void
    {
        $dispatcher ??= Events::getInstance();
        $dispatcher->on(get_called_class(), $callback);
    }
}
