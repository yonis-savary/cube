<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Parameters;

use Cube\Data\AutoDataToObject;

class OASParameter extends AutoDataToObject
{
    const IN_PATH = 'path';

    public function __construct(
        public string $name,
        public string $in,
        public bool $required,
        public array $schema = []
    )
    {
    }

    public function objectKeys(): array
    {
        return ['schema'];
    }
}