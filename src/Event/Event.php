<?php

namespace Cube\Event;

abstract class Event
{
    public function getName(): string
    {
        return static::class;
    }

    public function dispatch(?EventDispatcher $dispatcher = null): self
    {
        $dispatcher ??= Events::getInstance();
        $dispatcher->dispatch($this);

        return $this;
    }
}
