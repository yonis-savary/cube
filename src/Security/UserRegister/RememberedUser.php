<?php

namespace Cube\Security\UserRegister;

use Cube\Event\Event;
use Cube\Models\Model;

class RememberedUser extends Event
{
    public function __construct(
        public Model $userData,
        public mixed $userPrimaryKeyValue
    ) {}
}
