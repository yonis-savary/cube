<?php

namespace Cube\Data\OpenAPI\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class NoContentResponse
{
    public function __construct(
        public ?string $description = null
    ) {}
}
