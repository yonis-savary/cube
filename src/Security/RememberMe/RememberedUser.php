<?php

namespace Cube\Security\RememberMe;

use Cube\Data\Models\Model;
use Cube\Event\Event;

class RememberedUser extends Event
{
    public function __construct(
        public Model $userData,
        public mixed $userPrimaryKeyValue
    ) {}
}
