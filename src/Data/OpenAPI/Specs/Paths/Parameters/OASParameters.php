<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Parameters;

use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Router\Route;

class OASParameters extends AutoDataToObject
{
    use MakesSchemas;

    protected array $parameters = [];
    protected OpenAPIGenerationContext $context;

    public function toArray(): array
    {
        return Bunch::of($this->parameters)
            ->map(fn(OASParameter $parameter) => $parameter->toArray())
            ->toArray();
    }

    public function __construct(Route $route)
    {
        $this->context = OpenAPIGenerationContext::getInstance();
        $this->processSlugsParameters($route);

        $nonPostMethods = array_diff($route->getMethods(), ['PUT', 'PATCH', 'POST']);
        if (count($nonPostMethods))
            $this->processQueryParameters($route);
    }

    private function processSlugsParameters(Route $route)
    {
        $slugs = Bunch::fromExplode('/', $route->getPath())
            ->filter(fn ($part) => preg_match('/^\{.+\}$/', $part), true)
            ->toArray();

        $methodParameters = $route->getReflectionMethod()->getParameters();

        foreach ($slugs as $index => $slug) {
            $slug = substr($slug, 1, -1);

            if (str_contains($slug, ':')) {
                list($type, $name) = explode(':', $slug, 2);
                $parameter = new OASParameter($name, OASParameter::IN_PATH, true, []);
                $this->mutateParameterWithSlugType($type, $parameter->schema);
            }
            else
            {
                // Mirrors the Injector: slug values fill the parameters after the request, by position
                $slugParameter = $methodParameters[$index + 1] ?? null;
                $parameter = new OASParameter($slug, OASParameter::IN_PATH, true, []);
                if ($slugParameter)
                    $this->mutateParameterWithMethodType(
                        $this->getReflectionTypeName($slugParameter->getType()),
                        $parameter->schema
                    );
            }

            $this->context->log(" - Adding [" . $parameter->name . "] slug");
            $this->parameters[] = $parameter;
        }
    }
    public function processQueryParameters(Route $route) 
    {
        if (!$rules = $this->getRequestRules($route))
            return;

        foreach ($rules->getRules() as $key => $rule)
        {
            $this->context->log(" - Adding query [$key] param");
            $parameter = new OASParameter($key, 'query', !$rule->isNullable());
            $this->mutateParameterWithRule($rule, $parameter->schema);
            $this->parameters[] = $parameter;
        }
    }

}