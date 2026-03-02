<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Data\AutoDataToObject;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Web\Router\Route;

class OASResponses extends AutoDataToObject
{
    public array $responses = [];

    public function toArray(): array
    {
        return $this->responses;
    }

    public function __construct(Route $route)
    {
        $method = $route->getReflectionMethod();

        $modelResponses = $method->getAttributes(ModelResponse::class);
        foreach ($modelResponses as $responseReflectionAttribute)
        {
            $responseAttribute = $responseReflectionAttribute->newInstance();
            $response = new OASResponse();
            $response->modelResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }
    }
}