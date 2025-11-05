<?php 

namespace Cube\Security\Authentication;

use Cube\Data\Models\Model;

interface AuthenticationProvider {
    public function attempt(string $identifier, ?string $password=null): Model|false;
    public function userById(mixed $id): Model|false;
}