<?php

namespace Cube\Security\Authentication;

use Cube\Data\Models\Model;

interface VerifiesCredentials
{
    public function attempt(string $identifier, #[\SensitiveParameter] ?string $password = null): false|Model;
}
