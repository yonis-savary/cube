<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;

/**
 * Keys of what a `ModelResource` answers that may be null : the document adds null to their type
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Nullable
{
    /**
     * @param string[] $keys Dot-separated for nested ones, `*` standing for the items of a list (`'lines.*.discount'`)
     */
    public function __construct(
        public array $keys
    )
    {
    }
}
