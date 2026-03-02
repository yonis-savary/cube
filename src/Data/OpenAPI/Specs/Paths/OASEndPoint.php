<?php

namespace Cube\Data\OpenAPI\Specs\Paths;

use Cube\Data\AutoDataToObject;
use Cube\Data\OpenAPI\Attributes\Endpoint;
use Cube\Data\OpenAPI\OpenAPIGenerationContext;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Data\OpenAPI\Specs\Paths\Parameters\OASParameters;
use Cube\Data\OpenAPI\Specs\Paths\RequestBody\OASRequestBody;
use Cube\Data\OpenAPI\Specs\Paths\Responses\OASResponse;
use Cube\Data\OpenAPI\Specs\Paths\Responses\OASResponses;
use Cube\Utils\Text;
use Cube\Web\Router\Route;
use InvalidArgumentException;

class OASEndPoint extends AutoDataToObject
{
    use MakesSchemas;

    public string $summary = "";
    public string $description = "";
    public string $operationId = "";
    public OASParameters $parameters;
    public OASRequestBody $requestBody;
    public OASResponses $responses;

    public function objectKeys(): array
    {
        return ['responses', 'requestBody'];
    }

    public function skipOnEmpty(): array
    {
        return ['parameters', 'responses', 'requestBody'];
    }

    public function __construct(Route $route)
    {
        if (!is_array($route->getCallback())) {
            throw new InvalidArgumentException("Given route must have a Array-type callback");
        }

        $method = $route->getReflectionMethod();

        /** @var \ReflectionAttribute<Endpoint>[] $endpointDescriptions */
        $endpointDescriptions = $method->getAttributes(Endpoint::class);
        if ($description = array_pop($endpointDescriptions)) {
            $endpoint = $description->newInstance();
            $this->summary = $endpoint->summary;
            $this->description = $endpoint->description;
            $this->operationId = $endpoint->operationId ?? Text::camelCaseString($endpoint->summary);
        } else {
            [$controllerName, $methodName] = $route->getCallback();
            $this->summary = $methodName;
            $this->description = preg_replace("/.+\\\\/", '', $controllerName) . "." . $methodName;
            $this->operationId = Text::camelCaseString($this->summary);
        }

        $this->parameters = new OASParameters($route);
        $this->requestBody = new OASRequestBody($route);
        $this->responses = new OASResponses($route);
    }
}