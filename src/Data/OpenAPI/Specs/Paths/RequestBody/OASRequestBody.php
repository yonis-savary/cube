<?php

namespace Cube\Data\OpenAPI\Specs\Paths\RequestBody;

use Cube\Core\Autoloader;
use Cube\Data\AutoDataToObject;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Router\Route;
use ReflectionClass;

class OASRequestBody extends AutoDataToObject
{
    use MakesSchemas;

    public array $requestBody = [];

    public function toArray(): array
    {
        return $this->requestBody;
    }

    public function __construct(Route $route)
    {
        $nonPostMethods = array_diff($route->getMethods(), ['PUT', 'PATCH', 'POST']);
        if (!count($nonPostMethods))
            $this->processBodyParameters($route);
    }

    public function processBodyParameters(Route $route) 
    {
        $method = $route->getReflectionMethod();

        $requestParameter = array_values($method->getParameters())[0] ?? false;
        if (!$requestParameter)
            return;

        $requestType = $this->getReflectionTypeName($requestParameter->getType());
        if (!Autoloader::extends($requestType, Request::class))
            return;

        /** @var class-string<Request> $requestType */
        $request = new $requestType();
        $rules = $request->getRules();

        if (is_array($rules))
            $rules = new ObjectParam($rules);

        if (! $rules instanceof ObjectParam)
            return;

        if (!count($rules->getRules()))
            return;

        $this->requestBody = [];
        $this->requestBody['required'] = true;
        $this->requestBody['content'] = ['application/json' => ['schema' => []]];

        $this->mutateParameterWithRule(
            $rules,
            $this->requestBody['content']['application/json']['schema']
        );
    }
}