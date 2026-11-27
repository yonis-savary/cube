<?php

namespace Cube\Security\SingleSignOn;

use Cube\Env\Configuration\ConfigurationElement;

class SingleSignOnConfiguration extends ConfigurationElement
{
    /** @var IdentityProvider[] */
    public array $providers;

    public function __construct(IdentityProvider ...$providers)
    {
        $this->providers = array_values($providers);
    }
}
