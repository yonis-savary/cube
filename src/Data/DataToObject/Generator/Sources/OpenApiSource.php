<?php

namespace Cube\Data\DataToObject\Generator\Sources;

use Cube\Data\Bunch;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ClientAuthentication;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ClientOperation;
use Cube\Data\DataToObject\Generator\Descriptors\Client\DtoClient;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ServerVariable;
use Cube\Data\DataToObject\Generator\Descriptors\DtoClass;
use Cube\Data\DataToObject\Generator\Descriptors\DtoDirection;
use Cube\Data\DataToObject\Generator\Descriptors\DtoProperty;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ListType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\MapType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\MixedType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\Scalar;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ScalarType;
use Cube\Data\DataToObject\Generator\DtoNames;

/**
 * Reads an OpenAPI 3.0 or 3.1 document. Every object schema becomes a class of `Refs\Output` to read
 * responses, and of `Refs\Input` to build request bodies when an operation sends it. Each operation gets
 * a `Http\Requests\<Operation>Request` (its body, or its query string) and a
 * `Http\Responses\<Operation>Response` class, and is described for the client that calls it.
 *
 * @phpstan-type OpenApiObject array<string,mixed>
 */
class OpenApiSource implements DtoSource
{
    public const OUTPUT_NAMESPACE = 'Refs\Output';
    public const INPUT_NAMESPACE = 'Refs\Input';
    public const REQUESTS_NAMESPACE = 'Http\Requests';
    public const RESPONSES_NAMESPACE = 'Http\Responses';

    protected const SCHEMAS_POINTER = '#/components/schemas/';
    protected const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];
    protected const BODY_METHODS = ['POST', 'PUT', 'PATCH'];

    /** @var array<string,DtoClass> */
    protected array $classes = [];

    /** @var array<string,DtoType> */
    protected array $referenceTypes = [];

    /** @var ClientOperation[] */
    protected array $clientOperations = [];

    /** @var array<string,string> class => the sub-namespace its inline objects go to, under their own key as name */
    protected array $childNamespaces = [];

    protected bool $isRead = false;

    /**
     * @param array<string,mixed> $document
     * @param string[]            $endpoints   operationIds or "METHOD /path", every schema and operation when empty
     * @param ?string             $documentUrl where the document was fetched from, which a relative server URL is relative to
     */
    public function __construct(
        protected array $document,
        protected array $endpoints = [],
        protected ?string $documentUrl = null,
    ) {}

    public function toClasses(): array
    {
        $this->read();

        return array_values($this->classes);
    }

    public function toClient(): DtoClient
    {
        $this->read();

        $url = $this->serverUrl();

        return new DtoClient($url, $this->authentications(), $this->clientOperations, $this->serverVariables($url));
    }

    /**
     * @return string[]
     */
    public function unmatchedEndpoints(): array
    {
        $operations = $this->operations();

        return Bunch::wrap($this->endpoints)
            ->filter(fn (string $endpoint) => !Bunch::unzip($operations)->any(fn ($pair) => $this->matches($pair[0], $pair[1], $endpoint)))
            ->values()
            ->toArray()
        ;
    }

    protected function read(): void
    {
        if ($this->isRead)
            return;
        $this->isRead = true;

        if (!count($this->endpoints)) {
            foreach (array_keys($this->document['components']['schemas'] ?? []) as $name)
                $this->typeOfReference(self::SCHEMAS_POINTER.strtr($name, ['~' => '~0', '/' => '~1']), DtoDirection::Output);
        }

        foreach ($this->operations() as $route => $operation) {
            if (count($this->endpoints) && !$this->isSelected($route, $operation))
                continue;
            $this->addOperation($route, $operation);
        }
    }

    /**
     * @return array<string,array<string,mixed>> "METHOD /path" => operation, with the parameters of its path item
     */
    protected function operations(): array
    {
        $operations = [];
        foreach ($this->document['paths'] ?? [] as $path => $pathItem) {
            $pathItem = $this->resolve($pathItem);

            foreach (array_intersect_key($pathItem, array_flip(self::METHODS)) as $method => $operation) {
                $operation['parameters'] = $this->mergeParameters($pathItem['parameters'] ?? [], $operation['parameters'] ?? []);
                $operations[strtoupper($method).' '.$path] = $operation;
            }
        }

        return $operations;
    }

    /**
     * @param list<mixed> $pathItemParameters
     * @param list<mixed> $operationParameters
     *
     * @return list<OpenApiObject> an operation parameter overrides the path item one of the same name and location
     */
    protected function mergeParameters(array $pathItemParameters, array $operationParameters): array
    {
        $parameters = [];
        foreach ([...$pathItemParameters, ...$operationParameters] as $parameter) {
            $parameter = $this->resolve($parameter);
            $parameters[($parameter['in'] ?? '').':'.($parameter['name'] ?? '')] = $parameter;
        }

        return array_values($parameters);
    }

    /**
     * @param OpenApiObject $operation
     */
    protected function isSelected(string $route, array $operation): bool
    {
        return Bunch::wrap($this->endpoints)->any(fn (string $endpoint) => $this->matches($route, $operation, $endpoint));
    }

    /**
     * @param OpenApiObject $operation
     */
    protected function matches(string $route, array $operation, string $endpoint): bool
    {
        $endpoint = preg_replace('/\s+/', ' ', trim($endpoint));

        return $endpoint === ($operation['operationId'] ?? null)
            || preg_replace_callback('/^\S+/', fn ($method) => strtoupper($method[0]), $endpoint) === $route;
    }

    /**
     * @param OpenApiObject $operation
     */
    protected function addOperation(string $route, array $operation): void
    {
        [$method, $path] = explode(' ', $route, 2);
        $name = DtoNames::unique(
            DtoNames::className($operation['operationId'] ?? strtolower($method).' '.$path),
            Bunch::wrap($this->clientOperations)
                ->map(fn (ClientOperation $other) => $other->name)
                ->toArray(),
        );

        // Every placeholder of the path is an argument, typed as a string when the document forgets to declare it
        preg_match_all('/\{([^}]+)\}/', $path, $placeholders);
        $pathParameters = array_fill_keys($placeholders[1], new ScalarType(Scalar::String));

        $queryParameters = [];
        $queryDelimiters = [];
        foreach ($operation['parameters'] as $parameter) {
            $type = $this->typeOf($parameter['schema'] ?? [], self::REQUESTS_NAMESPACE.'\\'.$name.'Request', DtoNames::className($parameter['name']), DtoDirection::Input);

            if ('path' === $parameter['in'] && isset($pathParameters[$parameter['name']]))
                $pathParameters[$parameter['name']] = $type;

            if ('query' === $parameter['in'])
                $queryParameters[] = new DtoProperty($parameter['name'], $type, $this->isNullable($parameter['schema'] ?? []), true === ($parameter['required'] ?? false));

            if ('query' === $parameter['in'] && $type instanceof ListType && $delimiter = $this->delimiterOf($parameter))
                $queryDelimiters[$parameter['name']] = $delimiter;
        }

        $body = in_array($method, self::BODY_METHODS, true) ? $this->requestSchemaOf($operation) : null;

        // An empty schema (`{}`) accepts any JSON : it is still a body
        $request = null;
        if (null !== $body) {
            $request = $this->endpointType($name.'Request', $body, DtoDirection::Input);
        } elseif (count($queryParameters)) {
            $this->addClass(new DtoClass(self::REQUESTS_NAMESPACE, $name.'Request', $queryParameters, direction: DtoDirection::Input));
            $request = new ClassType(self::REQUESTS_NAMESPACE, $name.'Request');
        }

        $response = $this->responseSchemaOf($operation);

        $this->clientOperations[] = new ClientOperation(
            $name,
            $method,
            $path,
            $pathParameters,
            $request,
            null === $body && null !== $request,
            null !== $body
                ? true === ($this->resolve($operation['requestBody'])['required'] ?? false)
                : Bunch::wrap($queryParameters)->any(fn (DtoProperty $parameter) => $parameter->required),
            null !== $body ? $queryParameters : [],
            $response ? $this->endpointType($name.'Response', $response, DtoDirection::Output) : null,
            $this->securityOf($operation),
            $queryDelimiters,
        );
    }

    /**
     * What joins the items of a list sent as one query parameter, `null` when each item is a parameter of its
     * own : `explode` is `true` by default for the `form` style only. `deepObject` describes maps, which
     * `http_build_query()` already writes that way (`filter[status]=sold`).
     *
     * @param OpenApiObject $parameter
     */
    protected function delimiterOf(array $parameter): ?string
    {
        $style = $parameter['style'] ?? 'form';
        if ($parameter['explode'] ?? ('form' === $style))
            return null;
        return match ($style) {
            'form' => ',',
            'spaceDelimited' => '%20',
            'pipeDelimited' => '|',
            default => null,
        };
    }

    /**
     * @param OpenApiObject $operation
     *
     * @return ?OpenApiObject
     */
    protected function requestSchemaOf(array $operation): ?array
    {
        return $this->jsonSchemaOf($this->resolve($operation['requestBody'] ?? []));
    }

    /**
     * @param OpenApiObject $operation
     *
     * @return ?OpenApiObject
     */
    protected function responseSchemaOf(array $operation): ?array
    {
        foreach ($operation['responses'] ?? [] as $status => $response) {
            if (!preg_match('/^2(\d\d|XX)$/', (string) $status))
                continue;
            if ($schema = $this->jsonSchemaOf($this->resolve($response)))
                return $schema;
        }

        return null;
    }

    /**
     * @param OpenApiObject $bodyOrResponse
     *
     * @return ?OpenApiObject
     */
    protected function jsonSchemaOf(array $bodyOrResponse): ?array
    {
        foreach ($bodyOrResponse['content'] ?? [] as $mediaType => $media) {
            if (preg_match('~[/+]json\b~', $mediaType) && is_array($media['schema'] ?? null))
                return $media['schema'];
        }

        return null;
    }

    /**
     * An object body gets a class named after the endpoint, extending the schema class it refers to, or
     * holding the properties described inline, its own inline objects going to a sub-namespace of its name.
     * A list or a scalar body has no class of its own : its type is given as is.
     *
     * @param OpenApiObject $schema
     */
    protected function endpointType(string $name, array $schema, DtoDirection $direction): DtoType
    {
        $namespace = DtoDirection::Input === $direction ? self::REQUESTS_NAMESPACE : self::RESPONSES_NAMESPACE;
        $this->childNamespaces["{$namespace}\\{$name}"] = "{$namespace}\\{$name}";
        $type = $this->typeOf($schema, $namespace, $name, $direction);

        if (!$type instanceof ClassType || $namespace === $type->namespace)
            return $type;
        $this->addClass(new DtoClass($namespace, $name, parent: $type, direction: $direction));

        return new ClassType($namespace, $name);
    }

    protected function typeOfReference(string $pointer, DtoDirection $direction): DtoType
    {
        $key = $direction->name.$pointer;
        if ($type = $this->referenceTypes[$key] ?? null)
            return $type;
        $schema = $this->resolvePointer($pointer);
        $name = DtoNames::className(strtr(rawurldecode(preg_replace('~.+/~', '', $pointer)), ['~1' => '/', '~0' => '~']));
        $namespace = DtoDirection::Input === $direction ? self::INPUT_NAMESPACE : self::OUTPUT_NAMESPACE;

        // Set before recursing, so a schema that refers to itself ends up typed by its own class (or mixed)
        if (!$this->isClass($schema)) {
            $this->referenceTypes[$key] = new MixedType();

            return $this->referenceTypes[$key] = $this->typeOf($schema, $namespace, $name, $direction);
        }

        $this->referenceTypes[$key] = new ClassType($namespace, $name);
        $this->addClass($this->buildClass($namespace, $name, $schema, $direction));

        return $this->referenceTypes[$key];
    }

    protected function typeOf(mixed $schema, string $namespace, string $name, DtoDirection $direction): DtoType
    {
        if (!is_array($schema))
            return new MixedType();
        if (isset($schema['$ref']))
            return $this->typeOfReference($schema['$ref'], $direction);
        $variants = $this->nonNullVariantsOf($schema);
        if (count($variants))
            return 1 === count($variants) ? $this->typeOf($variants[0], $namespace, $name, $direction) : new MixedType();
        if (1 === count($schema['allOf'] ?? []) && !isset($schema['properties']))
            return $this->typeOf($schema['allOf'][0], $namespace, $name, $direction);
        if ($this->isObject($schema))
            return $this->objectTypeOf($schema, $namespace, $name, $direction);
        $types = array_values(array_diff((array) ($schema['type'] ?? []), ['null']));
        if (1 !== count($types))
            return new MixedType();
        $enum = array_values(array_filter($schema['enum'] ?? [], fn ($value) => null !== $value));

        return match ($types[0]) {
            'array' => new ListType($this->typeOf($schema['items'] ?? [], $namespace, $name.'Item', $direction)),
            'string' => new ScalarType(Scalar::String, $enum),
            'integer' => new ScalarType(Scalar::Int, $enum),
            'number' => new ScalarType(Scalar::Float, $enum),
            'boolean' => new ScalarType(Scalar::Bool),
            default => new MixedType(),
        };
    }

    /**
     * @param OpenApiObject $schema
     */
    protected function objectTypeOf(array $schema, string $namespace, string $name, DtoDirection $direction): DtoType
    {
        if ($this->isClass($schema)) {
            $this->addClass($this->buildClass($namespace, $name, $schema, $direction));

            return new ClassType($namespace, $name);
        }

        $additionalProperties = $schema['additionalProperties'] ?? null;

        return new MapType(is_array($additionalProperties)
            ? $this->typeOf($additionalProperties, $namespace, $name.'Value', $direction)
            : new MixedType());
    }

    /**
     * A `readOnly` property is never sent, a `writeOnly` one never received.
     *
     * @param OpenApiObject $schema
     */
    protected function buildClass(string $namespace, string $name, array $schema, DtoDirection $direction): DtoClass
    {
        $required = $this->requiredOf($schema);
        $excluded = DtoDirection::Input === $direction ? 'readOnly' : 'writeOnly';

        $childNamespace = $this->childNamespaces["{$namespace}\\{$name}"] ?? null;
        $childPrefix = null === $childNamespace ? $name : '';
        $childNamespace ??= $namespace;

        $properties = [];
        foreach ($this->propertiesOf($schema) as $key => $propertySchema) {
            if (is_array($propertySchema) && true === ($this->resolve($propertySchema)[$excluded] ?? false))
                continue;
            $properties[] = new DtoProperty(
                (string) $key,
                $this->typeOf($propertySchema, $childNamespace, $childPrefix.DtoNames::className((string) $key), $direction),
                $this->isNullable($propertySchema),
                in_array($key, $required, true),
            );
        }

        return new DtoClass($namespace, $name, $properties, direction: $direction);
    }

    protected function addClass(DtoClass $class): void
    {
        $fullName = ltrim("{$class->namespace}\\{$class->name}", '\\');

        if (isset($this->classes[$fullName]))
            throw new \RuntimeException("Two schemas of the OpenAPI document generate the class [{$fullName}] : rename one of them or select the endpoints to generate");

        $this->classes[$fullName] = $class;
    }

    /**
     * A relative server URL is relative to where the document was fetched from : left unknown for a file.
     * Its `{variables}` are left in place, the client fills them from its configuration.
     */
    protected function serverUrl(): ?string
    {
        $server = $this->document['servers'][0] ?? null;
        if (!is_array($server) || !isset($server['url']))
            return null;
        $url = $server['url'];

        if (preg_match('~^https?://~i', $url))
            return rtrim($url, '/');
        if (!$this->documentUrl || !preg_match('~^(https?:)//([^/?#]+)([^?#]*)~i', $this->documentUrl, $location))
            return null;
        [, $scheme, $host, $documentPath] = $location;

        $path = match (true) {
            str_starts_with($url, '//') => null,
            str_starts_with($url, '/') => $url,
            default => preg_replace('~[^/]*$~', '', $documentPath).$url,
        };

        if (null === $path)
            return rtrim($scheme.$url, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($segments);
                continue;
            }

            if ('.' !== $segment && '' !== $segment)
                $segments[] = $segment;
        }

        return rtrim("{$scheme}//{$host}/".implode('/', $segments), '/');
    }

    /**
     * Every placeholder of the server URL, declared or not. A default missing from the `enum` is one of its values.
     *
     * @return ServerVariable[]
     */
    protected function serverVariables(?string $url): array
    {
        if (null === $url)
            return [];
        preg_match_all('/\{([^}]+)\}/', $url, $placeholders);
        $declared = $this->document['servers'][0]['variables'] ?? [];

        $variables = [];
        foreach (array_unique($placeholders[1]) as $name) {
            $default = isset($declared[$name]['default']) ? (string) $declared[$name]['default'] : null;
            $enum = array_map(strval(...), $declared[$name]['enum'] ?? []);

            if (null !== $default && count($enum) && !in_array($default, $enum, true))
                $enum[] = $default;

            $variables[] = new ServerVariable($name, $default, $enum);
        }

        return $variables;
    }

    /**
     * Every security scheme the client can send. `oauth2` and `openIdConnect` flows are not run : the
     * configured credential is sent as a bearer token.
     *
     * @return array<string,ClientAuthentication>
     */
    protected function authentications(): array
    {
        $authentications = [];
        foreach ($this->document['components']['securitySchemes'] ?? [] as $name => $scheme) {
            $scheme = is_array($scheme) ? $this->resolve($scheme) : [];

            $authentication = match ($scheme['type'] ?? null) {
                'apiKey' => new ClientAuthentication($scheme['in'] ?? 'header', $scheme['name'] ?? (string) $name),
                'http' => match (strtolower($scheme['scheme'] ?? '')) {
                    'basic' => new ClientAuthentication('header', 'Authorization', 'Basic '),
                    'bearer' => new ClientAuthentication('header', 'Authorization', 'Bearer '),
                    default => null,
                },
                'oauth2', 'openIdConnect' => new ClientAuthentication('header', 'Authorization', 'Bearer '),
                default => null,
            };

            if ($authentication)
                $authentications[(string) $name] = $authentication;
        }

        return $authentications;
    }

    /**
     * The schemes of every alternative the operation accepts, its own `security` overriding the document's
     * (`security: []` requires none).
     *
     * @param OpenApiObject $operation
     *
     * @return string[]
     */
    protected function securityOf(array $operation): array
    {
        $requirements = $operation['security'] ?? $this->document['security'] ?? [];
        $supported = $this->authentications();

        return Bunch::wrap($requirements)
            ->flatMap(fn ($requirement) => is_array($requirement) ? array_map(strval(...), array_keys($requirement)) : [])
            ->filter(fn (string $scheme) => isset($supported[$scheme]))
            ->uniques()
            ->values()
            ->toArray()
        ;
    }

    /**
     * @param OpenApiObject $schema
     */
    protected function isClass(array $schema): bool
    {
        return $this->isObject($schema) && count($this->propertiesOf($schema));
    }

    /**
     * @param OpenApiObject $schema
     */
    protected function isObject(array $schema): bool
    {
        $schema = $this->resolve($schema);

        return in_array('object', (array) ($schema['type'] ?? []), true)
            || isset($schema['properties'])
            || Bunch::wrap($schema['allOf'] ?? [])->any(fn ($part) => is_array($part) && $this->isObject($part));
    }

    /**
     * @param OpenApiObject $schema
     *
     * @return array<string,mixed>
     */
    protected function propertiesOf(array $schema): array
    {
        $schema = $this->resolve($schema);

        $properties = [];
        foreach ($schema['allOf'] ?? [] as $part)
            $properties = [...$properties, ...$this->propertiesOf($part)];

        return [...$properties, ...$schema['properties'] ?? []];
    }

    /**
     * @param OpenApiObject $schema
     *
     * @return string[]
     */
    protected function requiredOf(array $schema): array
    {
        $schema = $this->resolve($schema);

        $required = $schema['required'] ?? [];
        foreach ($schema['allOf'] ?? [] as $part)
            $required = [...$required, ...$this->requiredOf($part)];

        return $required;
    }

    protected function isNullable(mixed $schema): bool
    {
        if (!is_array($schema))
            return true;
        if (true === ($schema['nullable'] ?? false)
            || in_array('null', (array) ($schema['type'] ?? []), true)
            || in_array(null, $schema['enum'] ?? [], true)
            || count($this->variantsOf($schema)) > count($this->nonNullVariantsOf($schema)))
                return true;
        if (isset($schema['$ref']))
            return $this->isNullable($this->resolve($schema));
        return 1 === count($schema['allOf'] ?? []) && $this->isNullable($schema['allOf'][0]);
    }

    /**
     * @param OpenApiObject $schema
     *
     * @return list<mixed>
     */
    protected function variantsOf(array $schema): array
    {
        return [...$schema['oneOf'] ?? [], ...$schema['anyOf'] ?? []];
    }

    /**
     * @param OpenApiObject $schema
     *
     * @return list<mixed>
     */
    protected function nonNullVariantsOf(array $schema): array
    {
        return Bunch::wrap($this->variantsOf($schema))
            ->filter(fn ($variant) => !is_array($variant) || ['null'] !== (array) ($variant['type'] ?? []))
            ->values()
            ->toArray()
        ;
    }

    /**
     * @param OpenApiObject $schema
     *
     * @return OpenApiObject
     */
    protected function resolve(array $schema): array
    {
        while (isset($schema['$ref']))
            $schema = $this->resolvePointer($schema['$ref']);

        return $schema;
    }

    /**
     * @return OpenApiObject
     */
    protected function resolvePointer(string $pointer): array
    {
        if (!str_starts_with($pointer, '#/'))
            throw new \InvalidArgumentException("Unsupported reference [{$pointer}] : only references inside the document (#/...) are supported");

        $target = $this->document;
        foreach (explode('/', substr($pointer, 2)) as $segment) {
            $segment = strtr(rawurldecode($segment), ['~1' => '/', '~0' => '~']);

            if (!is_array($target[$segment] ?? null))
                throw new \InvalidArgumentException("Reference [{$pointer}] does not point to an object of the OpenAPI document");

            $target = $target[$segment];
        }

        return $target;
    }
}
