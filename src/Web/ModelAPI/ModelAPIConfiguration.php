<?php

namespace Cube\Web\ModelAPI;

use Cube\Env\Configuration\ConfigurationElement;

class ModelAPIConfiguration extends ConfigurationElement
{
    public function __construct(
        public array $middlewares = [],
        public array $routeExtras = []
    ) {}
}
