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
}