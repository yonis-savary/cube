<?php

namespace Cube\Data\OpenAPI\Specs\Paths\Responses;

use Cube\Core\Autoloader;
use Cube\Data\AutoDataToObject;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Models\Model;
use Cube\Data\Models\ModelResource;
use Cube\Data\Models\RelationTree;
use Cube\Data\Models\Relations\HasMany;
use Cube\Data\OpenAPI\Attributes\ModelResponse;
use Cube\Data\OpenAPI\Attributes\Nullable;
use Cube\Data\OpenAPI\Attributes\ObjectResponse;
use Cube\Data\OpenAPI\Attributes\Optional;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Attributes\ResourceResponse;
use Cube\Data\OpenAPI\Attributes\ResourceSchema;
use Cube\Data\OpenAPI\Specs\Common\MakesSchemas;
use Cube\Utils\Utils;
use InvalidArgumentException;
use ReflectionClass;
use stdClass;

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
        $schema = $this->toEncodableSchema($this->getResourceSchema($resourceResponse->resourceClass, $data));

        $this->description = $resourceResponse->description ?? '';
        $this->content[$resourceResponse->mimeType] = $resourceResponse->isArray
            ? ['schema' => ['type' => 'array', 'items' => $schema], 'example' => [$data]]
            : ['schema' => $schema, 'example' => $data];
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
            return $this->getResourceSchema($schema->resourceClass, $this->getResourceExample($schema->resourceClass, $schema->with));

        if (is_string($schema) && Autoloader::extends($schema, ModelResource::class))
            return $this->getResourceSchema($schema, $this->getResourceExample($schema));

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
     * The example gives the types, the resource's #[Nullable] and #[Optional] what it cannot show
     *
     * @param class-string<ModelResource<Model,array<string,mixed>>> $resourceClass
     * @param array<mixed> $example
     * @return array<string,mixed>
     */
    private function getResourceSchema(string $resourceClass, array $example): array
    {
        $resource = new ReflectionClass($resourceClass);
        $nullableKeys = ($resource->getAttributes(Nullable::class)[0] ?? null)?->newInstance()->keys ?? [];
        $optionalKeys = ($resource->getAttributes(Optional::class)[0] ?? null)?->newInstance()->keys ?? [];

        $schema = $this->getRawDataSchema($example);

        $unknownKey = Bunch::wrap([...$nullableKeys, ...$optionalKeys])->diff($this->getSchemaPaths($schema))->first();
        if ($unknownKey !== null)
            throw new InvalidArgumentException("{$resourceClass} declares the key [{$unknownKey}], which is not in what its toArray() answers");

        return $this->withDeclaredKeys($schema, $nullableKeys, $optionalKeys);
    }

    /**
     * @param array<string,mixed>|stdClass $schema
     * @return string[]
     */
    private function getSchemaPaths(array|stdClass $schema, string $path = ''): array
    {
        $paths = [];
        foreach ($this->getChildSchemas($schema) as $key => $child) {
            $childPath = $this->getChildPath($path, $key);
            $paths = [...$paths, $childPath, ...$this->getSchemaPaths($child, $childPath)];
        }

        return $paths;
    }

    /**
     * @param array<string,mixed>|stdClass $schema
     * @param string[] $nullableKeys
     * @param string[] $optionalKeys
     * @return array<string,mixed>|stdClass
     */
    private function withDeclaredKeys(array|stdClass $schema, array $nullableKeys, array $optionalKeys, string $path = ''): array|stdClass
    {
        if ($schema instanceof stdClass)
            return $schema;

        $children = $this->getChildSchemas($schema);
        foreach ($children as $key => $child)
            $children[$key] = $this->withDeclaredKeys($child, $nullableKeys, $optionalKeys, $this->getChildPath($path, $key));

        if ($schema['type'] === 'array' && $children)
            $schema['items'] = $children['*'];

        if ($schema['type'] === 'object') {
            $schema['properties'] = $children;
            $required = Bunch::wrap(array_keys($children))
                ->filter(fn ($key) => !in_array($this->getChildPath($path, $key), $optionalKeys, true))
                ->values()
                ->toArray();
            if ($required)
                $schema['required'] = $required;
        }

        if (in_array($path, $nullableKeys, true))
            $schema['type'] = [$schema['type'], 'null'];

        return $schema;
    }

    /**
     * @param array<string,mixed>|stdClass $schema
     * @return array<string,array<string,mixed>|stdClass>
     */
    private function getChildSchemas(array|stdClass $schema): array
    {
        if ($schema instanceof stdClass)
            return [];

        return match ($schema['type']) {
            'object' => $schema['properties'],
            'array' => isset($schema['items']) ? ['*' => $schema['items']] : [],
            default => [],
        };
    }

    private function getChildPath(string $path, string|int $key): string
    {
        return $path === '' ? (string) $key : "{$path}.{$key}";
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