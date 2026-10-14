<?php 

namespace Cube\Security\Authentication;

use Cube\Data\Models\Model;

interface AuthenticationProvider {
    public function attempt(string $identifier, #[\SensitiveParameter] ?string $password=null): Model|false;
    public function userById(mixed $id): Model|false;

    /**
     * @return string[] Fields of the user model holding credentials, never stored in the session
     */
    public function getCredentialFields(): array;
}