<?php

namespace Cube\Data\OpenAPI\Configuration\Authentication;

use Cube\Data\OpenAPI\Configuration\OpenApiConfigElement;

interface OpenApiAuthScheme extends OpenApiConfigElement
{
    public function getComponentName(): string;
}