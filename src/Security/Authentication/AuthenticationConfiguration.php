<?php 

namespace Cube\Security\Authentication;

use Cube\Env\Configuration\ConfigurationElement;

class AuthenticationConfiguration extends ConfigurationElement
{
    public function __construct(public AuthenticationProvider $provider)
    {}
}