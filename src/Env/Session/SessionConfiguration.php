<?php

namespace Cube\Env\Session;

use Cube\Configuration\ConfigurationElement;

class SessionConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $namespace = 'cube'
    ) {}
}
