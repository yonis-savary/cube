<?php

namespace Cube\Security\Authentication;

use Cube\Configuration\ConfigurationElement;

class AuthenticationConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $model = 'App\Models\User',
        public readonly array|string $loginFields = ['email'],
        public readonly string $passwordField = 'password',
        public readonly ?string $saltField = null,
    ) {}
}
