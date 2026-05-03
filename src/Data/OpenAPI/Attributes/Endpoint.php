<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Endpoint
{
    /**
     * If `$operationId` is left null, a camelCase of the summary shall be generated
     */
    public function __construct(
        public string $summary,
        public string $description = "",
        public ?string $operationId = null
    )
    {
    }
}