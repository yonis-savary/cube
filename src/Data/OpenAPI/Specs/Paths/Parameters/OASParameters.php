<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Parameters;

use Cube\Core\Autoloader;
use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\ObjectParam;
use Cube\Web\Router\Route;
use ReflectionClass;

class OASParameters extends AutoDataToObject
{
    use MakesSchemas;

    protected array $parameters = [];

    public function toArray(): array
    {
        return $this->parameters;
    }

    public function __construct(Route $route)
    {
        $this->processSlugsParameters($route);

        $nonPostMethods = array_diff($route->getMethods(), ['PUT', 'PATCH', 'POST']);
        if (count($nonPostMethods))
            $this->processQueryParameters($route);
    }

    private function processSlugsParameters(Route $route)
    {
        $routePath = $route->getPath();
        if (!str_contains($routePath, '{'))
            return;

        $parts = explode('/', $routePath);

        foreach ($parts as &$part) {
            if (!$part)
                continue;

            if (!preg_match('/^\{.+\}$/', $part))
                continue;

            $part = substr($part, 1, strlen($part) - 2);

            $name = $part;
            $type = "any";

            if (str_contains($part, ':')) {
                list($type, $name) = explode(':', $part, 2);
                $parameter = new OASParameter($name, OASParameter::IN_PATH, true, []);
                $this->mutateParameterWithSlugType($type, $parameter->schema);
            }
            else
            {
                $method = $route->getReflectionMethod();

                $parameters = Bunch::of($method->getParameters());
                $slugParameter = $parameters->first(fn($param) => $param->getName() === $name);
                $parameter = new OASParameter($name, OASParameter::IN_PATH, true, []);
                if (!$slugParameter) {
                    return $parameter;
                }

                $type = $slugParameter->getType();
                $this->mutateParameterWithMethodType(
                    $this->getReflectionTypeName($type), 
                    $parameter->schema
                );
            }

            $this->parameters[] = $parameter;
        }
    }
    public function processQueryParameters(Route $route) 
    {
        list($controllerName, $methodName) = $route->getCallback();
        $controller = new ReflectionClass($controllerName);
        $method = $controller->getMethod($methodName);


        $requestParameter = array_values($method->getParameters())[0] ?? false;
        if (!$requestParameter) {
            return;
        }
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

        foreach ($rules->getRules() as $key => $rule)
        {
            $parameter = new OASParameter($key, 'query', $rule->isNullable());
            $this->mutateParameterWithRule($rule, $parameter->schema);
        }
    }

}