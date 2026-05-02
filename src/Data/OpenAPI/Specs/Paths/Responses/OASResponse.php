<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Data\AutoDataToObject;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Data\OpenAPI\Specs\Common\ModelRef;

class OASResponse extends AutoDataToObject
{
    use ModelRef;
    use MakesSchemas;

    public ?string $description = null;
    public array $content = [];

    public function __construct()
    {}

    public function skipOnEmpty(): array
    {
        return ['description'];
    }

    public function modelResponse(ModelResponse $modelResponse)
    {
        $this->description = $modelResponse->description;
        $ref = ['$ref' => $this->getRefForClass($modelResponse->modelClass)];
        $this->content[$modelResponse->mimeType] =  $modelResponse->isArray
            ? ['schema' => ['type' => 'array', 'items' => $ref]]
            : ['schema' => $ref];
    }

    public function rawResponse(RawResponse $rawResponse) {
        $this->description = $rawResponse->description;
        $schema = [ 'schema' => [] ];
        $this->mutateParameterFromRawData($rawResponse->data, $schema['schema']);
        $this->content[$rawResponse->mimeType] = $schema;
    }
}