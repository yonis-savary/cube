<?php

namespace Cube\Core\Autoloader;

use Cube\Configuration\ConfigurationElement;

class AutoloaderConfiguration extends ConfigurationElement
{
    /**
     * @param bool $cached If `true`, `Autoloader` will cache the class list and extends/uses/implements requests results
     */
    public function __construct(
        public readonly bool $cached=true
    ){}
}