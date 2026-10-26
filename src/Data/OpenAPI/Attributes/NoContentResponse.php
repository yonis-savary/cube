<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class NoContentResponse
{
    public function __construct(
        public ?string $description = null
    )
    {
    }
}
