<?php

namespace Cube\Event;

abstract class EventDispatcher
{
    /** @var array<string,callable[]> */
    protected array $subscriptions = [];

    /**
     * @template TEvent
     *
     * @param class-string<TEvent>|string  $eventName
     * @param \Closure(TEvent):void $callback
     */
    public function on(string $eventName, callable $callback): self
    {
        if (!array_key_exists($eventName, $this->subscriptions)) {
            $this->subscriptions[$eventName] = [];
        }

        $this->subscriptions[$eventName][] = $callback;

        return $this;
    }

    public function dispatch(Event|string $event): void
    {
        if (is_string($event)) {
            $event = new CustomEvent($event);
        }

        foreach ($this->subscriptions[$event->getName()] ?? [] as $callback) {
            $callback($event);
        }
    }
}
