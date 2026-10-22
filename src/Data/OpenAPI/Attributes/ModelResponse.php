<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;
use Cube\Data\Models\Model;
use Cube\Web\Http\StatusCode;

#[Attribute(Attribute::TARGET_METHOD)]
class ModelResponse
{
    /**
     * @param class-string<Model> $modelClass
     * @param string[] $with Relations the endpoint loads, dot-separated for nested ones : the schema requires them
     */
    public function __construct(
        public string $modelClass,
        public bool $isArray=false,
        public int $responseCode=StatusCode::OK,
        public ?string $description = null,
        public string $mimeType = "application/json",
        public array $with = []
    )
    {
    }
}