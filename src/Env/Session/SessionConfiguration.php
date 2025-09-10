<?php

namespace Cube\Env\Session;

use Cube\Env\Configuration\ConfigurationElement;

class SessionConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $namespace = 'cube'
    ) {}
}
