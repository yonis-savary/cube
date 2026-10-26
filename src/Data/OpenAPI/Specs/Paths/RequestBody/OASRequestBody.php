<?php

namespace Cube\Data\OpenAPI\Specs\Paths\RequestBody;

use Cube\Data\AutoDataToObject;
use Cube\Data\OpenAPI\Attributes\RawBody;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Router\Route;

class OASRequestBody extends AutoDataToObject
{
    use MakesSchemas;

    public array $requestBody = [];
    protected OpenAPIGenerationContext $context;

    public function toArray(): array
    {
        return $this->requestBody;
    }

    public function __construct(Route $route)
    {
        $nonPostMethods = array_diff($route->getMethods(), ['PUT', 'PATCH', 'POST']);
        $this->context = OpenAPIGenerationContext::getInstance();
        if (!count($nonPostMethods))
            $this->processBodyParameters($route);

    }

    public function processBodyParameters(Route $route) 
    {
        if ($rawBody = $route->getReflectionMethod()->getAttributes(RawBody::class)[0] ?? null) {
            $this->processRawBody($rawBody->newInstance());
            return;
        }

        if (!$rules = $this->getRequestRules($route))
            return;

        $this->requestBody = [];
        $this->requestBody['required'] = true;
        $this->requestBody['content'] = ['application/json' => ['schema' => []]];

        $keys = $rules instanceof ObjectParam ? array_keys($rules->getRules()) : [];
        foreach ($keys as $key)
            $this->context->log(" - Adding body request [$key] parameter");

        $this->mutateParameterWithRule(
            $rules,
            $this->requestBody['content']['application/json']['schema']
        );
        $this->requestBody['content']['application/json']['schema'] = $this->toEncodableSchema(
            $this->requestBody['content']['application/json']['schema']
        );
    }

    private function processRawBody(RawBody $rawBody): void
    {
        $this->context->log(" - Adding raw [{$rawBody->mimeType}] body");

        $this->requestBody['required'] = true;
        $this->requestBody['content'] = [$rawBody->mimeType => ['schema' => ['type' => 'string', 'format' => 'binary']]];
        if ($rawBody->description)
            $this->requestBody['description'] = $rawBody->description;
    }
}