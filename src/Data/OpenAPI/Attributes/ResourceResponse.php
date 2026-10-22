<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;
use Cube\Data\Models\ModelResource;
use Cube\Web\Http\StatusCode;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ResourceResponse
{
    /**
     * @param class-string<ModelResource> $resourceClass
     * @param string[] $with Relations the endpoint loads, dot-separated for nested ones : the example model gets them
     */
    public function __construct(
        public string $resourceClass,
        public bool $isArray=false,
        public int $responseCode=StatusCode::OK,
        public ?string $description = null,
        public string $mimeType = "application/json",
        public array $with = []
    )
    {
    }
}
