<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Data\AutoDataToObject;
use Cube\Data\Database\Database;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Attributes\ResourceResponse;
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

    // A resource only has toArray() : its shape is read from what it answers for a model its factory made
    public function resourceResponse(ResourceResponse $resourceResponse)
    {
        $resourceClass = $resourceResponse->resourceClass;
        $modelClass = $resourceClass::getModelClass();

        $model = $modelClass::factory()->withSeed(crc32($modelClass))->make();

        // A resource may load relations : on an empty database in dry-run mode, the example never reads a real row
        $exampleDatabase = new Database();
        $data = $exampleDatabase->dryRun(fn () => Database::withInstance($exampleDatabase, fn () => $resourceClass::of($model)));
        $example = $resourceResponse->isArray ? [$data] : $data;

        $this->rawResponse(new RawResponse($example, description: $resourceResponse->description, mimeType: $resourceResponse->mimeType));
        $this->content[$resourceResponse->mimeType]['example'] = $example;
    }

    public function rawResponse(RawResponse $rawResponse) {
        $this->description = $rawResponse->description ?? '';
        $schema = [ 'schema' => [] ];
        $this->mutateParameterFromRawData($rawResponse->data, $schema['schema']);
        $this->content[$rawResponse->mimeType] = $schema;
    }
}