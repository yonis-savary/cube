<?php

namespace Cube\Data\OpenAPI\Specs\Paths\RequestBody;

use Cube\Data\AutoDataToObject;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
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
        $rules = $this->getRequestRules($route);
        if (!$rules?->getRules())
            return;

        $this->requestBody = [];
        $this->requestBody['required'] = true;
        $this->requestBody['content'] = ['application/json' => ['schema' => []]];

        foreach ($rules->getRules() as $key => $_) {
            $this->context->log(" - Adding body request [$key] parameter");
        }

        $this->mutateParameterWithRule(
            $rules,
            $this->requestBody['content']['application/json']['schema']
        );
    }
}