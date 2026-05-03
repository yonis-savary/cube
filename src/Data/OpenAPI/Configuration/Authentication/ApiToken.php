<?php

namespace Cube\Data\OpenAPI\Configuration\Authentication;

use Cube\Data\OpenAPI\Configuration\OpenApiConfigElement;
use Override;

class ApiToken implements OpenApiAuthScheme
{
    public function __construct(
        public string $headerName="X-API-Token",
        public string $componentNameInDoc="ApiTokenAuth"
    )
    {}

    public function getComponentName(): string
    {
        return $this->componentNameInDoc;
    }

    public function toArray(): array
    {
        return [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => $this->headerName
        ];
    }
}