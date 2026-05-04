<?php

namespace Cube\Security\Authentication\Events;

use Cube\Event\Event;
use Cube\Data\Models\Model;

class LoggedOutUser extends Event
{
    public function __construct(
        public Model $authenticatedUser,
        public mixed $userId
    ) {}
}
