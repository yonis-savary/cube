<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;
use Cube\Web\Http\StatusCode;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ObjectResponse
{
    /**
     * @param array<mixed> $schema An example value, where a `ModelResource` class (or a `ResourceSchema` naming its relations) stands for what it answers and a `Model` class for its schema
     */
    public function __construct(
        public array $schema,
        public int $responseCode=StatusCode::OK,
        public ?string $description = null,
        public string $mimeType = "application/json"
    )
    {
    }
}
