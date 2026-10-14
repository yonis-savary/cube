<?php

namespace Cube\Tests\Units\Env\Classes;

use Cube\Security\Authentication;
use Cube\Security\Authentication\AuthenticationConfiguration;
use Cube\Security\Authentication\PasswordAuthentication;
use Cube\Tests\Units\Models\User;

class SupportInbox extends TeamInbox
{
    public static function getAuthentication(): Authentication
    {
        return new class(new AuthenticationConfiguration(new PasswordAuthentication(User::class, 'login', 'password'))) extends Authentication {
            public function userId(): mixed
            {
                return 42;
            }
        };
    }
}
