<?php

namespace Cube\Data\OpenAPI;

use Cube\Data\OpenAPI\Configuration\Authentication\OpenApiAuthScheme;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Env\Storage;
use Cube\Utils\Path;

class OpenAPIConfiguration extends ConfigurationElement
{
    /**
     * @param ?string $outputFile Output file (relative to project root), (openapi.json in your Storage if null is given)
     */
    public function __construct(
        public ?string $outputFile = null,
        public string $title = "Application",
        public string $version = "0.0.1",
        public ?OpenApiAuthScheme $authenticationScheme=null,
        public int $jsonFlags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        public bool $displayLogs = true,
    )
    {
        $this->outputFile = $this->outputFile
            ? Path::relative($this->outputFile)
            : Storage::getInstance()->path("openapi.json");
    }
}