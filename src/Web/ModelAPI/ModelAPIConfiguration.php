<?php

namespace YonisSavary\Cube\Web\ModelAPI;

use YonisSavary\Cube\Configuration\ConfigurationElement;

class ModelAPIConfiguration extends ConfigurationElement
{
    public function __construct(
        public array $middlewares=[],
        public array $routeExtras=[]
    ){}
}