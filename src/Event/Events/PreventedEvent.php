<?php

namespace Cube\Event\Events;

use Cube\Event\Event;

class PreventedEvent extends Event {
    public function __construct(
        public readonly Event $event
    )
    {}
}