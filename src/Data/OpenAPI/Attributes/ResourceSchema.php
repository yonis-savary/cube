<?php

namespace Cube\Data\OpenAPI\Attributes;

use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;

/**
 * A resource inside an `#[ObjectResponse]` schema, with the relations the endpoint loads for it
 */
class ResourceSchema
{
    /**
     * @param class-string<ModelResource<Model,array<string,mixed>>> $resourceClass
     * @param string[] $with Relations the endpoint loads, dot-separated for nested ones : the example model gets them
     */
    public function __construct(
        public string $resourceClass,
        public array $with = []
    )
    {
    }
}
