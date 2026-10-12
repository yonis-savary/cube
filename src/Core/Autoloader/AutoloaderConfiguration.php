<?php

namespace Cube\Core\Autoloader;

use Cube\Env\Configuration\ConfigurationElement;

use function Cube\isDebug;
use function Cube\isProduction;

class AutoloaderConfiguration extends ConfigurationElement
{
    public readonly bool $cached;
    public readonly bool $displayErrors;

    /**
     * @param bool $cached If `true`, `Autoloader` will cache the class list and extends/uses/implements requests results
     * @param bool $displayErrors If `false`, PHP warnings only reach the error log instead of being printed into the response
     */
    public function __construct(
        ?bool $cached = null,
        ?bool $displayErrors = null,
    ) {
        $this->cached = $cached ?? isProduction();
        $this->displayErrors = $displayErrors ?? isDebug();
    }
}
