<?php

namespace Cube\Data\OpenAPI;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Env\Storage;

class OpenAPIGeneratorConfiguration extends ConfigurationElement
{
    public function __construct(
        public ?string $outputFile = null,
        public string $title = "Application",
        public string $version = "0.0.1",
        public int $jsonFlags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        public ?Storage $storage = null
    )
    {
        $storage ??= Storage::getInstance();
        $this->outputFile = $storage->path($outputFile);
    }
}