<?php

namespace Cube\Core\Autoloader;

use Cube\Env\Configuration\ConfigurationElement;

use function Cube\env;

class AutoloaderConfiguration extends ConfigurationElement
{
    public readonly bool $cached;

    /**
     * @param bool $cached If `true`, `Autoloader` will cache the class list and extends/uses/implements requests results
     */
    public function __construct(
        ?bool $cached = null
    ) {
        $this->cached = $cached ?? str_starts_with(strtolower(env('env', 'debug')), 'prod');
    }
}
