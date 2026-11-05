<?php

namespace Cube\Security\Authentication\Events;

use Cube\Data\Models\Model;
use Cube\Event\Event;

class LoggedOutUser extends Event
{
    public function __construct(
        public Model $authenticatedUser,
        public mixed $userId
    ) {}
}
