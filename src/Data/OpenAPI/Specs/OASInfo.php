<?php

namespace Cube\Data\OpenAPI\Specs;

use Cube\Data\AutoDataToObject;

class OASInfo extends AutoDataToObject
{
    public function __construct(
        public string $title,
        public string $version
    ){}
}