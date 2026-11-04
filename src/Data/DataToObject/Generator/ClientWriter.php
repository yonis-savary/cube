<?php

namespace Cube\Data\DataToObject\Generator;

use Cube\Data\Bunch;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ClientOperation;
use Cube\Data\DataToObject\Generator\Descriptors\Client\DtoClient;
use Cube\Data\DataToObject\Generator\Descriptors\DtoProperty;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ListType;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Utils\Text;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

/**
 * Writes an `HttpClient` with one method per operation, and the configuration element it reads its URL
 * and credentials from. Both are named after the last segment of the namespace (`SpotifyClient`, `SpotifyConfiguration`)
 */
class ClientWriter
{
    protected const HELPER_METHODS = ['send', 'credentials', 'successful'];

    public function __construct(
        protected GenerationTarget $target,
        protected string $generator,
    ) {}

    /**
     * @return string[] written files
     */
    public function write(DtoClient $client): array
    {
        return [
            $this->target->write('', $this->configurationName(), $this->renderConfiguration($client)),
            $this->target->write('', $this->clientName(), $this->renderClient($client)),
        ];
    }

    public function clientName(): string
    {
        return $this->baseName().'Client';
    }

    public function configurationName(): string
    {
        return $this->baseName().'Configuration';
    }

    /**
     * The configuration property holding the credential of each security scheme, after `url` : two schemes
     * giving the same name (`api_key` and `apiKey`) get a number
     *
     * @return array<string,string> scheme => property
     */
    public function credentialPropertiesOf(DtoClient $client): array
    {
        $properties = [];
        foreach (array_keys($client->authentications) as $scheme)
            $properties[(string) $scheme] = DtoNames::unique(
                lcfirst(DtoNames::propertyName((string) $scheme)),
                ['url', ...$properties]
            );

        return $properties;
    }

    public function renderConfiguration(DtoClient $client): string
    {
        $parameters = [
            '        public readonly ?string $url = '.(null === $client->url ? 'null' : var_export($client->url, true)).',',
            ...array_map(fn (string $property) => '        public readonly ?string $'.$property.' = null,', array_values($this->credentialPropertiesOf($client))),
        ];

        return $this->file($this->configurationName(), ConfigurationElement::class, Text::interpolate(Text::toFile('
                public function __construct(
            {parameters}
                ) {}
            ', 3), ['parameters' => implode("\n", $parameters)]), new PhpReferences($this->target->namespace, $this->configurationName(), [ConfigurationElement::class]));
    }

    public function renderClient(DtoClient $client): string
    {
        $references = new PhpReferences($this->target->namespace, $this->clientName(), [
            HttpClient::class,
            Request::class,
            Response::class,
            ...Bunch::wrap($client->operations)
                ->flatMap(fn (ClientOperation $operation) => [
                    ...($operation->request ? TypeRenderer::classesOf($operation->request, deep: true) : []),
                    ...($operation->response ? TypeRenderer::classesOf($operation->response, deep: true) : []),
                ])
                ->map(fn (ClassType $type) => $this->target->fullNameOf($type))
                ->toArray(),
        ]);
        $types = new TypeRenderer($this->target, $references, objectsAtAnyDepth: true);

        $readsLists = Bunch::wrap($client->operations)->any(fn (ClientOperation $operation) => $this->isListOfClasses($operation->response));
        $authenticates = count($client->authentications) > 0;

        $blocks = [
            $this->baseUrlMethod(),
            ...Bunch::wrap($client->operations)->map(fn (ClientOperation $operation) => $this->operationMethod($operation, $types))->toArray(),
            $this->sendMethod($references, $authenticates),
            ...($authenticates ? [$this->credentialsMethod($client)] : []),
            ...($readsLists ? [$this->successfulMethod($references)] : []),
        ];

        return $this->file($this->clientName(), HttpClient::class, implode("\n\n", $blocks), $references);
    }

    protected function baseUrlMethod(): string
    {
        return Text::interpolate(Text::toFile('
                public function baseURL(): ?string
                {
                    return {configuration}::resolve()->url;
                }
            ', 3), ['configuration' => $this->configurationName()]);
    }

    /**
     * A path parameter, the request and a query parameter can give the same PHP name (`{id}` and `?id=`) :
     * the later ones get a number, in the order of the signature
     *
     * @return array{path:array<string,string>,request:?string,query:array<string,string>} placeholder or key => PHP name
     */
    protected function argumentNamesOf(ClientOperation $operation): array
    {
        $taken = [];

        $path = [];
        foreach (array_keys($operation->pathParameters) as $placeholder)
            $taken[] = $path[$placeholder] = DtoNames::unique(
                DtoNames::propertyName((string) $placeholder),
                $taken
            );

        $request = $operation->request
            ? DtoNames::unique($this->requestArgumentName($operation->request), $taken)
            : null;

        if ($request)
            $taken[] = $request;

        $query = [];
        foreach ($operation->queryParameters as $parameter)
            $taken[] = $query[$parameter->key] = DtoNames::unique(
                DtoNames::propertyName($parameter->key),
                $taken
            );

        return ['path' => $path, 'request' => $request, 'query' => $query];
    }

    protected function operationMethod(ClientOperation $operation, TypeRenderer $types): string
    {
        $names = $this->argumentNamesOf($operation);
        $arguments = [];
        $docs = [];

        foreach ($operation->pathParameters as $name => $type)
            $arguments[] = $types->nativeTypeOf($type).' $'.$names['path'][$name];

        if ($request = $operation->request) {
            $argument = $names['request'];
            $nativeType = $types->nativeTypeOf($request);
            $arguments[] = $operation->requestRequired || 'mixed' === $nativeType
                ? "{$nativeType} \${$argument}"
                : "?{$nativeType} \${$argument} = null";

            if ($types->needsDocType($request))
                $docs[] = '@param '.$types->docTypeOf($request).' $'.$argument;
        }

        list($requiredQuery, $optionalQuery) = Bunch::wrap($operation->queryParameters)->partitionFilter(fn (DtoProperty $parameter) => !$parameter->required);
        foreach ([...$requiredQuery, ...$optionalQuery] as $parameter) {
            $nativeType = $types->nativeTypeOf($parameter->type);
            $arguments[] = $parameter->required || 'mixed' === $nativeType
                ? $nativeType.' $'.$names['query'][$parameter->key]
                : '?'.$nativeType.' $'.$names['query'][$parameter->key].' = null';
        }

        if ($this->isListOfClasses($operation->response))
            $docs[] = '@return '.$types->docTypeOf($operation->response);

        [$returnType, $return] = $this->returnOf($operation, $types, $this->callOf($operation, $names));

        $doc = $docs ? "    /**\n".implode("\n", array_map(fn (string $tag) => "     * {$tag}", $docs))."\n     */\n" : '';

        return $doc.Text::interpolate(Text::toFile('
                public function {name}({arguments}): {returnType}
                {
                    {return}
                }
            ', 3), [
            'name' => $this->methodName($operation),
            'arguments' => implode(', ', $arguments),
            'returnType' => $returnType,
            'return' => $return,
        ]);
    }

    /**
     * @param array{path:array<string,string>,request:?string,query:array<string,string>} $names
     */
    protected function callOf(ClientOperation $operation, array $names): string
    {
        $argument = $names['request']
            ? '$'.$names['request']
            : null;

        $query = match (true) {
            $operation->requestInQuery => $operation->requestRequired ? "{$argument}->toArray()" : "{$argument}?->toArray() ?? []",
            count($operation->queryParameters) > 0 => 'array_filter(['
                .Bunch::wrap($operation->queryParameters)
                    ->map(fn (DtoProperty $parameter) => var_export($parameter->key, true).' => $'.$names['query'][$parameter->key])
                    ->join(', ')
                .'], fn ($value) => null !== $value)',
            default => null,
        };

        $body = match (true) {
            null === $argument || $operation->requestInQuery => null,
            $operation->request instanceof ClassType => "{$argument}->toArray()",
            $this->isListOfClasses($operation->request) => "array_map(fn (\$item) => \$item->toArray(), {$argument})",
            default => $argument,
        };

        $schemes = count($operation->security)
            ? '['.implode(', ', array_map(fn (string $scheme) => var_export($scheme, true), $operation->security)).']'
            : null;

        return '$this->send('.implode(', ', [
            var_export($operation->method, true),
            $this->pathExpressionOf($operation, $names['path']),
            ...(null !== $query ? ["query: {$query}"] : []),
            ...(null !== $body ? ["body: {$body}"] : []),
            ...(null !== $schemes ? ["schemes: {$schemes}"] : []),
        ]).')';
    }

    /**
     * @return array{string,string} return type, return statement
     */
    protected function returnOf(ClientOperation $operation, TypeRenderer $types, string $call): array
    {
        $response = $operation->response;

        if ($response instanceof ClassType)
            return [$types->nameOf($response), "return {$call}->toObject({$types->nameOf($response)}::class);"];

        if ($response instanceof ListType && $response->item instanceof ClassType)
            return ['array', "return {$types->nameOf($response->item)}::array(\$this->successful({$call})->getJSON());"];

        return [$types->referenceOf(Response::class), "return {$call};"];
    }

    protected function sendMethod(PhpReferences $references, bool $authenticates): string
    {
        $template = $authenticates ? '
                /**
                 * @param array<string,mixed> $query
                 * @param string[]            $schemes the security schemes the operation accepts : the configured ones are sent
                 */
                protected function send(string $method, string $path, array $query = [], mixed $body = null, array $schemes = []): {response}
                {
                    [$headers, $credentials] = $this->credentials($schemes);

                    if (null !== $body)
                        $headers[\'content-type\'] = \'application/json\';

                    return (new {request}($method, $path, [...$query, ...$credentials], [], $headers, body: null === $body ? \'\' : json_encode($body, JSON_THROW_ON_ERROR)))->fetch(httpClient: $this);
                }
            ' : '
                /**
                 * @param array<string,mixed> $query
                 */
                protected function send(string $method, string $path, array $query = [], mixed $body = null): {response}
                {
                    $headers = null === $body ? [] : [\'content-type\' => \'application/json\'];

                    return (new {request}($method, $path, $query, [], $headers, body: null === $body ? \'\' : json_encode($body, JSON_THROW_ON_ERROR)))->fetch(httpClient: $this);
                }
            ';

        return Text::interpolate(Text::toFile($template, 3), [
            'request' => $references->nameOf(Request::class),
            'response' => $references->nameOf(Response::class),
        ]);
    }

    protected function credentialsMethod(DtoClient $client): string
    {
        $properties = $this->credentialPropertiesOf($client);

        $statements = Bunch::unzip($client->authentications)
            ->map(function (array $pair) use ($properties) {
                [$scheme, $authentication] = $pair;
                $value = '$configuration->'.$properties[$scheme];

                $assignment = match ($authentication->location) {
                    'query' => '$query['.var_export($authentication->name, true)."] = {$value};",
                    'cookie' => "\$headers['Cookie'] = ".var_export($authentication->name.'=', true).".{$value};",
                    default => '$headers['.var_export($authentication->name, true).'] = '.($authentication->prefix ? var_export($authentication->prefix, true).'.' : '')."{$value};",
                };

                return '        if (in_array('.var_export($scheme, true).", \$schemes, true) && null !== {$value})\n            {$assignment}";
            })
            ->join("\n\n");

        return Text::interpolate(Text::toFile('
                /**
                 * @param string[] $schemes
                 *
                 * @return array{array<string,string>,array<string,string>} headers, query parameters
                 */
                protected function credentials(array $schemes): array
                {
                    $configuration = {configuration}::resolve();
                    $headers = [];
                    $query = [];

            {statements}

                    return [$headers, $query];
                }
            ', 3), [
            'configuration' => $this->configurationName(),
            'statements' => $statements,
        ]);
    }

    protected function successfulMethod(PhpReferences $references): string
    {
        return Text::interpolate(Text::toFile('
                protected function successful({response} $response): {response}
                {
                    if (!$response->isOk())
                        throw new \RuntimeException("Could not read the response : the API answered {$response->getStatusCode()}");

                    return $response;
                }
            ', 3), ['response' => $references->nameOf(Response::class)]);
    }

    /**
     * `/invoices/{id}/lines` becomes `'/invoices/'.rawurlencode((string) $id).'/lines'`
     *
     * @param array<string,string> $pathNames placeholder => PHP name
     */
    protected function pathExpressionOf(ClientOperation $operation, array $pathNames): string
    {
        $fragments = [];
        foreach (preg_split('/\{([^}]+)\}/', $operation->path, flags: PREG_SPLIT_DELIM_CAPTURE) as $index => $part) {
            $fragments[] = match (true) {
                0 === $index % 2 => '' === $part ? null : var_export($part, true),
                isset($pathNames[$part]) => 'rawurlencode((string) $'.$pathNames[$part].')',
                default => var_export('{'.$part.'}', true),
            };
        }

        return implode('.', array_filter($fragments));
    }

    protected function methodName(ClientOperation $operation): string
    {
        $name = lcfirst($operation->name);

        $taken = array_map(strtolower(...), [...get_class_methods(HttpClient::class), ...self::HELPER_METHODS]);

        return in_array(strtolower($name), $taken, true) ? $name.'Operation' : $name;
    }

    protected function requestArgumentName(DtoType $request): string
    {
        return match (true) {
            $request instanceof ClassType => 'request',
            $this->isListOfClasses($request) => 'items',
            default => 'body',
        };
    }

    protected function isListOfClasses(?DtoType $type): bool
    {
        return $type instanceof ListType && $type->item instanceof ClassType;
    }

    protected function file(string $name, string $parent, string $body, PhpReferences $references): string
    {
        return Text::interpolate(Text::toFile('
            <?php

            namespace {namespace};

            {uses}/**
             * Generated by {generator}, regenerate it rather than editing it
             */
            class {name} extends {parent}
            {
            {body}
            }
            ', 3), [
            'namespace' => $this->target->namespace,
            'uses' => $references->toUses(),
            'generator' => $this->generator,
            'name' => $name,
            'parent' => $references->nameOf($parent),
            'body' => $body,
        ])."\n";
    }

    protected function baseName(): string
    {
        return preg_replace('/.+\\\/', '', $this->target->namespace);
    }
}
