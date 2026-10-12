<?php

namespace Cube\Security\Authentication\Events;

use Cube\Event\Event;

class FailedAuthentication extends Event
{
    public function __construct(
        public string $identifier
    ) {}
}
