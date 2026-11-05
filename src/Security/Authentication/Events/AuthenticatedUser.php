<?php

namespace Cube\Security\Authentication\Events;

use Cube\Data\Models\Model;
use Cube\Event\Event;

/**
 * Dispatched at the same time as LoggedInUser.
 */
class AuthenticatedUser extends Event
{
    public function __construct(
        public Model $authenticatedUser,
        public mixed $userId
    ) {}
}
