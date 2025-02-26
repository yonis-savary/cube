<?php

namespace Cube\Web\ModelAPI;

use Cube\Configuration\ConfigurationElement;

class ModelAPIConfiguration extends ConfigurationElement
{
    public function __construct(
        public array $middlewares = [],
        public array $routeExtras = []
    ) {}
}
