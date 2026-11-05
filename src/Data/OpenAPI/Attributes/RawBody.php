<?php

namespace Cube\Data\OpenAPI\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RawBody
{
    public function __construct(
        public string $mimeType = 'application/octet-stream',
        public ?string $description = null
    ) {}
}
