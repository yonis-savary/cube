<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Core\Autoloader;
use Cube\Data\AutoDataToObject;
use Cube\Data\Database\Database;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Data\Models\RelationTree;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\ObjectResponse;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Attributes\ResourceResponse;
use Cube\Data\OpenAPI\Attributes\ResourceSchema;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Utils\Utils;

class OASResponse extends AutoDataToObject
{
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

    public function noContentResponse(?string $description = null)
    {
        $this->description = $description ?? 'No content';
    }

    public function modelResponse(ModelResponse $modelResponse)
    {
        $this->description = $modelResponse->description ?? '';
        $ref = ['$ref' => $this->getRefForClass($modelResponse->modelClass)];
        if ($modelResponse->with) {
            $relationTree = (new RelationTree(...$modelResponse->with))->getTree();
            $ref = ['allOf' => [$ref, $this->getLoadedRelationsSchema($modelResponse->modelClass, $relationTree)]];
        }

        $this->content[$modelResponse->mimeType] =  $modelResponse->isArray
            ? ['schema' => ['type' => 'array', 'items' => $ref]]
            : ['schema' => $ref];
    }

    public function resourceResponse(ResourceResponse $resourceResponse)
    {
        $data = $this->getResourceExample($resourceResponse->resourceClass, $resourceResponse->with);
        $example = $resourceResponse->isArray ? [$data] : $data;

        $this->rawResponse(new RawResponse($example, description: $resourceResponse->description, mimeType: $resourceResponse->mimeType));
        $this->content[$resourceResponse->mimeType]['example'] = $example;
    }

    public function objectResponse(ObjectResponse $objectResponse): void
    {
        $this->description = $objectResponse->description ?? '';
        $this->content[$objectResponse->mimeType] = ['schema' => $this->toEncodableSchema($this->getObjectSchema($objectResponse->schema))];
    }

    /**
     * @return array<string,mixed>
     */
    private function getObjectSchema(mixed $schema): array
    {
        if ($schema instanceof ResourceSchema)
            return $this->getRawDataSchema($this->getResourceExample($schema->resourceClass, $schema->with));

        if (is_string($schema) && Autoloader::extends($schema, ModelResource::class))
            return $this->getRawDataSchema($this->getResourceExample($schema));

        if (is_string($schema) && Autoloader::extends($schema, Model::class))
            return ['$ref' => $this->getRefForClass($schema)];

        if (!is_array($schema) || $schema === [])
            return $this->getRawDataSchema($schema);

        if (Utils::isList($schema))
            return ['type' => 'array', 'items' => $this->toEncodableSchema($this->getObjectSchema($schema[0]))];

        return [
            'type' => 'object',
            'properties' => array_map(fn ($value) => $this->toEncodableSchema($this->getObjectSchema($value)), $schema),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function getRawDataSchema(mixed $data): array
    {
        $schema = [];
        $this->mutateParameterFromRawData($data, $schema);
        return $schema;
    }

    /**
     * A resource only has toArray() : its shape is read from what it answers for a model its factory made
     *
     * @param class-string<ModelResource<Model,array<string,mixed>>> $resourceClass
     * @param string[] $with
     * @return array<mixed>
     */
    private function getResourceExample(string $resourceClass, array $with = []): array
    {
        $modelClass = $resourceClass::getModelClass();

        $model = $modelClass::factory()
            ->withSeed(crc32($modelClass))
            ->with(...$with)
            ->make();

        // A resource may load relations : on an empty database in dry-run mode, the example never reads a real row
        $exampleDatabase = new Database();
        return $exampleDatabase->dryRun(fn () => Database::withInstance($exampleDatabase, fn () => $resourceClass::of($model)));
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<string,array<mixed>> $relationTree
     * @return array<string,mixed>
     */
    private function getLoadedRelationsSchema(string $modelClass, array $relationTree): array
    {
        $instance = new $modelClass();
        $schema = ['required' => array_keys($relationTree)];

        foreach ($relationTree as $name => $subtree) {
            if (!$subtree)
                continue;

            $relation = $instance->{$name}();
            $subschema = $this->getLoadedRelationsSchema($relation->toModel, $subtree);
            $schema['properties'][$name] = $relation instanceof HasMany
                ? ['items' => $subschema]
                : $subschema;
        }

        return $schema;
    }

    public function rawResponse(RawResponse $rawResponse) {
        $this->description = $rawResponse->description ?? '';
        if ($rawResponse->isBinary()) {
            $this->content[$rawResponse->mimeType] = ['schema' => ['type' => 'string', 'format' => 'binary']];
            return;
        }

        $schema = [];
        $this->mutateParameterFromRawData($rawResponse->data, $schema);
        $this->content[$rawResponse->mimeType] = ['schema' => $this->toEncodableSchema($schema)];
    }
}