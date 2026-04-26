<?php

namespace Cube\Data\OpenAPI;

use Cube\Core\Component;

class OpenAPIGenerationContext
{
    use Component;

    /**
     * @var class-string<Model>[] $usedModelRefs List of used model class names
     */
    public array $usedModelRefs = [];

    public function __construct(
        public readonly OpenAPIConfiguration $configuration
    )
    {
    }

    public function log(string ...$elements) {
        if (!$this->configuration->displayLogs)
            return;

        foreach ($elements as $element) {
            if (!str_ends_with($element, "\n"))
                $element .= "\n";

            print($element);
        }
    }
}