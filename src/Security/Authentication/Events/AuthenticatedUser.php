<?php

namespace Cube\Security\Authentication\Events;

use Cube\Event\Event;
use Cube\Models\Model;

class AuthenticatedUser extends Event
{
    public function __construct(
        public Model $authenticatedUser,
        public mixed $userId
    ) {}
}
