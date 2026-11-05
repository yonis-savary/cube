<?php

namespace Cube\Data\OpenAPI\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class NoContentResponse
{
    public function __construct(
        public ?string $description = null
    ) {}
}
