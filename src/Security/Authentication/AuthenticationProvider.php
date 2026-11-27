<?php

namespace Cube\Security\Authentication;

use Cube\Data\Models\Model;

interface AuthenticationProvider
{
    public function userById(mixed $id): false|Model;

    /**
     * @return string[] Fields of the user model holding credentials, never stored in the session
     */
    public function getCredentialFields(): array;
}
