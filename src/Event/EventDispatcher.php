<?php

namespace Cube\Event;

use Cube\Event\Events\PreventedEvent;

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

    /** @return bool Was the Event dispatched successfully ? */
    public function dispatch(Event|string $event): bool
    {
        if (is_string($event)) {
            $event = new CustomEvent($event);
        }

        if (!array_key_exists($event->getName(), $this->subscriptions)) {
            return true;
        }

        foreach ($this->subscriptions[$event->getName()] ?? [] as $callback) {
            $callback($event);

            if ($event->isPrevented()) {
                if ($event::class !== PreventedEvent::class)
                    (new PreventedEvent($event))->dispatch($this);
                return false;
            }
        }

        return true;
    }
}
