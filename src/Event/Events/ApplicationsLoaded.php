<?php

namespace Cube\Event\Events;

use Cube\Event\Event;

class ApplicationsLoaded extends Event {
    public function __construct(
        public readonly array $loaded = []
    )
    {}
}