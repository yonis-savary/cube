<?php

namespace Cube\Data\DataToObject\Generator\Descriptors\Client;

use Cube\Data\DataToObject\Generator\Descriptors\DtoProperty;
use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;

readonly class ClientOperation
{
    /**
     * @param string                $name            PascalCased, from the operationId or the method and path
     * @param array<string,DtoType> $pathParameters  name in the path => type
     * @param ?DtoType              $request         the body, or the query string when `$requestInQuery`
     * @param bool                  $requestRequired a body is always required, a query string when one of its parameters is
     * @param DtoProperty[]         $queryParameters query parameters of an operation that also has a body
     * @param ?DtoType              $response        `null` when the response is not JSON
     * @param string[]              $security        the security schemes the operation accepts
     */
    public function __construct(
        public string $name,
        public string $method,
        public string $path,
        public array $pathParameters = [],
        public ?DtoType $request = null,
        public bool $requestInQuery = false,
        public bool $requestRequired = true,
        public array $queryParameters = [],
        public ?DtoType $response = null,
        public array $security = [],
    ) {}
}
