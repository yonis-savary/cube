<?php

namespace Cube\Data\OpenAPI\Attributes;

use Attribute;

/**
 * Keys of what a `ModelResource` answers that may be missing : the document leaves them out of `required`
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Optional
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
