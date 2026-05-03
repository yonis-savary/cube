<?php

namespace Cube\Data\OpenAPI\Configuration\Authentication;

class BearerToken implements OpenApiAuthScheme
{
    /**
     * @param string $format Can be anything, is not limited by openapi official docs
     */
    public function __construct(
        public string $format="JWT",
        public string $componentNameInDoc="BearerAuth"
    )
    {}

    public function getComponentName(): string
    {
        return $this->componentNameInDoc;
    }

    public function toArray(): array
    {
        return [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => $this->format
        ];
    }
}