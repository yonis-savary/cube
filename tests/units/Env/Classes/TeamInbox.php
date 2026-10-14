<?php

namespace Cube\Tests\Units\Env\Classes;

use Cube\Env\UserComponent;

class TeamInbox
{
    use UserComponent;

    public function __construct(
        public readonly mixed $owner
    ) {}

    public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static
    {
        return new static($userPrimaryKey);
    }
}
