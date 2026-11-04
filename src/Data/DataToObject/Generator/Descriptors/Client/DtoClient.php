<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Client;

readonly class DtoClient
{
    /**
     * @param ?string                             $url             the first server URL of the document, made absolute, its `{variables}` left in place
     * @param array<string,ClientAuthentication> $authentications security scheme name => how its credential is sent
     * @param ClientOperation[]                   $operations
     * @param ServerVariable[]                    $serverVariables the variables of `$url`, in their order
     */
    public function __construct(
        public ?string $url,
        public array $authentications,
        public array $operations,
        public array $serverVariables = [],
    ) {}
}
