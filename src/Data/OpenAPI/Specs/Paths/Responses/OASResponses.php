<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Core\Autoloader;
use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\Models\Model;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use ReflectionNamedType;
use ReflectionParameter;

class OASResponses extends AutoDataToObject
{
    use MakesSchemas;

    public array $responses = [];

    public function toArray(): array
    {
        return $this->responses;
    }

    public function __construct(Route $route)
    {
        $method = $route->getReflectionMethod();

        if ($this->getRequestRules($route)?->getRules())
        {
            $response = new OASResponse();
            $response->validationErrorsResponse();
            $this->responses[StatusCode::UNPROCESSABLE_CONTENT] = $response->toArray();
        }

        if ($this->receivesAModelSlug($route))
        {
            $response = new OASResponse();
            $response->notFoundResponse();
            $this->responses[StatusCode::NOT_FOUND] = $response->toArray();
        }

        $modelResponses = $method->getAttributes(ModelResponse::class);
        foreach ($modelResponses as $responseReflectionAttribute)
        {
            $responseAttribute = $responseReflectionAttribute->newInstance();
            $response = new OASResponse();
            $response->modelResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }

        $rawResponses = $method->getAttributes(RawResponse::class);
        foreach ($rawResponses as $rawResponseAttribute)
        {
            $responseAttribute = $rawResponseAttribute->newInstance();
            $response = new OASResponse();
            $response->rawResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }
    }

    protected function receivesAModelSlug(Route $route): bool
    {
        $slugCount = Bunch::fromExplode('/', $route->getPath())
            ->filter(fn ($part) => preg_match('/^\{.+\}$/', $part))
            ->count();

        return Bunch::of($route->getReflectionMethod()->getParameters())
            ->slice(1, $slugCount)
            ->any(fn (ReflectionParameter $parameter) =>
                $parameter->getType() instanceof ReflectionNamedType
                && Autoloader::extends($parameter->getType()->getName(), Model::class)
            );
    }
}
