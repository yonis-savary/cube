<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Data\Models\Model;
use Cube\Security\Authentication\PasswordAuthentication;
use Cube\Security\Authentication\VerifiesBearerTokens;
use Cube\Tests\Units\Models\User;

class BearerTokenAuthentication extends PasswordAuthentication implements VerifiesBearerTokens
{
    public const ALICE_TOKEN = 'alice-api-token';

    public function __construct()
    {
        parent::__construct(User::class, 'login', 'password');
    }

    public function userByBearerToken(#[\SensitiveParameter] string $token): false|Model
    {
        if (self::ALICE_TOKEN !== $token)
            return false;
        return User::findWhere(['login' => 'alice']) ?? false;
    }
}
