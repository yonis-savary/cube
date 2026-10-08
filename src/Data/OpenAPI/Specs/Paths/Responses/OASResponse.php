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

    public string $description = '';
    public array $content = [];

    const VALIDATION_ERRORS_SCHEMA = [
        'anyOf' => [
            ['type' => 'array', 'items' => ['type' => 'string']],
            ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/ValidationErrors']],
        ],
    ];

    public function __construct()
    {}

    public function skipOnEmpty(): array
    {
        return ['content'];
    }

    public function validationErrorsResponse()
    {
        $this->description = 'The request does not follow its rules, errors are keyed by field';
        $ref = ['$ref' => $this->getRefForSchema('ValidationErrors', self::VALIDATION_ERRORS_SCHEMA)];
        $this->content['application/json'] = ['schema' => $ref];
    }

    public function notFoundResponse()
    {
        $this->description = 'No item matches the given slug';
    }

    public function noContentResponse()
    {
        $this->description = 'No content';
    }

    public function modelResponse(ModelResponse $modelResponse)
    {
        $this->description = $modelResponse->description ?? '';
        $ref = ['$ref' => $this->getRefForClass($modelResponse->modelClass)];
        $this->content[$modelResponse->mimeType] =  $modelResponse->isArray
            ? ['schema' => ['type' => 'array', 'items' => $ref]]
            : ['schema' => $ref];
    }

    public function rawResponse(RawResponse $rawResponse) {
        $this->description = $rawResponse->description ?? '';
        $schema = [ 'schema' => [] ];
        $this->mutateParameterFromRawData($rawResponse->data, $schema['schema']);
        $this->content[$rawResponse->mimeType] = $schema;
    }
}