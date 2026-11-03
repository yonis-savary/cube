<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Client;

readonly class DtoClient
{
    /**
     * @param ?string                             $url             the first server URL of the document, made absolute
     * @param array<string,ClientAuthentication> $authentications security scheme name => how its credential is sent
     * @param ClientOperation[]                   $operations
     */
    public function __construct(
        public ?string $url,
        public array $authentications,
        public array $operations,
    ) {}
}
