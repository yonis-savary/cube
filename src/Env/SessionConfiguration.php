<?php

namespace YonisSavary\Cube\Env;

use YonisSavary\Cube\Configuration\ConfigurationElement;
use YonisSavary\Cube\Core\Autoloader;

class SessionConfiguration extends ConfigurationElement
{
    public readonly string $name;

    public function __construct(?string $name=null)
    {
        $this->name = $name ?? md5(Autoloader::getProjectPath());
    }
}