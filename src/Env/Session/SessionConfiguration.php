<?php

namespace YonisSavary\Cube\Env\Session;

use YonisSavary\Cube\Configuration\ConfigurationElement;

class SessionConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly ?string $namespace=null
    ){}
}