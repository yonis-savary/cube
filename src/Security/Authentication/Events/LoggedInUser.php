<?php

namespace Cube\Security\Authentication\Events;

use Cube\Event\Event;
use Cube\Data\Models\Model;

/**
 * Dispatched at the same time as AuthenticatedUser
 */
class LoggedInUser extends Event
{
    public function __construct(
        public Model $authenticatedUser,
        public mixed $userId
    ) {}
}
