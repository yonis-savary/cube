<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Client;

readonly class ServerVariable
{
    /**
     * @param string   $name    its placeholder in the server URL, `tenant` for `{tenant}`
     * @param ?string  $default `null` when the document gives none : the URL cannot be built until it is configured
     * @param string[] $enum    the values it may take, none when it is free
     */
    public function __construct(
        public string $name,
        public ?string $default = null,
        public array $enum = [],
    ) {}
}
