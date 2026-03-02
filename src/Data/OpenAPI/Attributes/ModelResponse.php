<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;
use Cube\Web\Http\StatusCode;

#[Attribute(Attribute::TARGET_METHOD)]
class ModelResponse
{
    public function __construct(
        public string $modelClass,
        public bool $isArray=false,
        public int $responseCode=StatusCode::OK,
        public ?string $description = null,
        public string $mimeType = "application/json"
    )
    {
    }
}