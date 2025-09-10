<?php

namespace Cube\Security\RememberMe;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Env\Cache;

class UserRegisterConfiguration extends ConfigurationElement
{
    public function __construct(
        public string $cookieName = 'remember-me-token',
        public int $cookieDuration = Cache::WEEK * 2,
        public bool $refreshTokenOnRemember = true,
        public bool $cookieSecure = true,
        public bool $cookieHttpOnly = true
    ) {}
}
