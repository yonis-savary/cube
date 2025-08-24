<?php

namespace Cube\Security\RememberMe;

use Cube\Event\Event;
use Cube\Data\Models\Model;

class RememberedUser extends Event
{
    public function __construct(
        public Model $userData,
        public mixed $userPrimaryKeyValue
    ) {}
}
