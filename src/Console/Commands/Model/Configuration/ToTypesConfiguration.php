<?php

namespace Cube\Console\Commands\Model\Configuration;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Env\Storage;
use Cube\Utils\Path;

class ToTypesConfiguration extends ConfigurationElement
{
    /**
     * @return ?string $ouptputFile Output file (relative to project root), (cube-types.ts in your Storage if null is given)
     */
    public function __construct(
        public ?string $outputFile=null
    )
    {
        $this->outputFile = $this->outputFile
            ? Path::relative($this->outputFile)
            : Storage::getInstance()->path("cube-types.ts");
    }
}