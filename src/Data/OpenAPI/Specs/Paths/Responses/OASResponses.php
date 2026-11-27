<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Core\Autoloader;
use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\Models\Model;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\NoContentResponse;
use Cube\Data\OpenAPI\Attributes\ObjectResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Attributes\ResourceResponse;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;

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

        // From the least to the most specific : a later declaration of the same status code replaces an earlier one
        foreach ($route->getMiddlewares() as $middleware)
            $this->addDeclaredResponses(new \ReflectionClass($middleware));
        $this->addDeclaredResponses(new \ReflectionClass($route->getCallback()[0]));

        if ($this->getRequestRules($route)) {
            $response = new OASResponse();
            $response->validationErrorsResponse();
            $this->responses[StatusCode::UNPROCESSABLE_CONTENT] = $response->toArray();
        }

        if ($this->receivesAModelSlug($route)) {
            $response = new OASResponse();
            $response->notFoundResponse();
            $this->responses[StatusCode::NOT_FOUND] = $response->toArray();
        }

        $this->addReturnTypeResponses($method);
        $this->addDeclaredResponses($method);
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $source
     */
    protected function addDeclaredResponses(\ReflectionClass|\ReflectionMethod $source): void
    {
        foreach ($source->getAttributes(ModelResponse::class) as $modelReflectionAttribute) {
            $responseAttribute = $modelReflectionAttribute->newInstance();
            $response = new OASResponse();
            $response->modelResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }

        foreach ($source->getAttributes(ResourceResponse::class) as $resourceReflectionAttribute) {
            $responseAttribute = $resourceReflectionAttribute->newInstance();
            $response = new OASResponse();
            $response->resourceResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }

        foreach ($source->getAttributes(ObjectResponse::class) as $objectReflectionAttribute) {
            $responseAttribute = $objectReflectionAttribute->newInstance();
            $response = new OASResponse();
            $response->objectResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }

        foreach ($source->getAttributes(RawResponse::class) as $rawReflectionAttribute) {
            $responseAttribute = $rawReflectionAttribute->newInstance();
            $response = new OASResponse();
            $response->rawResponse($responseAttribute);
            $this->responses[$responseAttribute->responseCode] = $response->toArray();
        }

        foreach ($source->getAttributes(NoContentResponse::class) as $noContentReflectionAttribute)
            $this->addNoContentResponse($noContentReflectionAttribute->newInstance()->description);
    }

    protected function addReturnTypeResponses(\ReflectionMethod $method): void
    {
        $type = $method->getReturnType();
        if (!$type instanceof \ReflectionNamedType)
            return;
        if ('void' === $type->getName()) {
            $this->addNoContentResponse();
            return;
        }

        if (!Autoloader::extends($type->getName(), Model::class))
            return;
        $response = new OASResponse();
        $response->modelResponse(new ModelResponse($type->getName()));
        $this->responses[StatusCode::OK] = $response->toArray();

        if ($type->allowsNull())
            $this->addNoContentResponse();
    }

    protected function addNoContentResponse(?string $description = null): void
    {
        $response = new OASResponse();
        $response->noContentResponse($description);
        $this->responses[StatusCode::NO_CONTENT] = $response->toArray();
    }

    protected function receivesAModelSlug(Route $route): bool
    {
        $slugCount = Bunch::fromExplode('/', $route->getPath())
            ->filter(fn ($part) => preg_match('/^\{.+\}$/', $part))
            ->count()
        ;

        return Bunch::wrap($route->getReflectionMethod()->getParameters())
            ->slice(1, $slugCount)
            ->any(
                fn (\ReflectionParameter $parameter) => $parameter->getType() instanceof \ReflectionNamedType
                && Autoloader::extends($parameter->getType()->getName(), Model::class)
            )
        ;
    }
}
