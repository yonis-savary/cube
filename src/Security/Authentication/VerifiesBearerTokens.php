<?php

namespace Cube\Security\Authentication;

use Cube\Data\Models\Model;

interface VerifiesBearerTokens
{
    public function userByBearerToken(#[\SensitiveParameter] string $token): false|Model;
}
