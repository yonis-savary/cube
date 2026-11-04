<?php

namespace Cube\Data\DataToObject\Generator;

use Cube\Data\Bunch;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ClientAuthentication;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ClientOperation;
use Cube\Data\DataToObject\Generator\Descriptors\Client\DtoClient;
use Cube\Data\DataToObject\Generator\Descriptors\Client\ServerVariable;
use Cube\Data\DataToObject\Generator\Descriptors\DtoProperty;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ClassType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\DtoType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ListType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\MapType;
use Cube\Data\DataToObject\Generator\Descriptors\Types\Scalar;
use Cube\Data\DataToObject\Generator\Descriptors\Types\ScalarType;
use Cube\Env\Configuration\ConfigurationElement;
use Cube\Utils\Text;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

/**
 * Writes an `HttpClient` with one method per operation, and the configuration element it reads its URL
 * and credentials from. Both are named after `$name`, by default the last segment of the namespace
 * (`SpotifyClient`, `SpotifyConfiguration`)
 */
class ClientWriter
{
    protected const HELPER_METHODS = ['send', 'credentials', 'successful', 'toQueryString', 'withDelimiters'];

    /**
     * @param ?string $name what the client and its configuration are named after, `Spotify` for `SpotifyClient`
     */
    public function __construct(
        protected GenerationTarget $target,
        protected string $generator,
        protected ?string $name = null,
    ) {}

    /**
     * @return string[] written files
     */
    public function write(DtoClient $client): array
    {
        $files = [];

        $enums = $this->serverVariableEnumsOf($client);
        foreach ($client->serverVariables as $variable) {
            if (!isset($enums[$variable->name]))
                continue;

            $files[] = $this->target->write('', $enums[$variable->name], $this->renderEnum($enums[$variable->name], $variable));
        }

        return [
            ...$files,
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
                ['url', ...array_values($this->serverVariablePropertiesOf($client)), ...$properties]
            );

        return $properties;
    }

    /**
     * The configuration property of each server variable, after `url`
     *
     * @return array<string,string> variable => property
     */
    public function serverVariablePropertiesOf(DtoClient $client): array
    {
        $properties = [];
        foreach ($client->serverVariables as $variable)
            $properties[$variable->name] = DtoNames::unique(lcfirst(DtoNames::propertyName($variable->name)), ['url', ...$properties]);

        return $properties;
    }

    /**
     * The enum of each server variable that declares its values, `HelpdeskTenant` for `{tenant}`
     *
     * @return array<string,string> variable => enum name
     */
    public function serverVariableEnumsOf(DtoClient $client): array
    {
        $enums = [];
        foreach ($client->serverVariables as $variable) {
            if (!count($variable->enum))
                continue;

            $enums[$variable->name] = DtoNames::unique($this->baseName().DtoNames::className($variable->name), [$this->clientName(), $this->configurationName(), ...$enums]);
        }

        return $enums;
    }

    public function renderConfiguration(DtoClient $client): string
    {
        $variables = $this->serverVariablePropertiesOf($client);
        $enums = $this->serverVariableEnumsOf($client);

        // A URL with variables is built by the client from their configured values : `url` only overrides it
        $url = null === $client->url || count($variables) ? 'null' : var_export($client->url, true);

        $parameters = [
            "        public readonly ?string \$url = {$url},",
            ...Bunch::wrap($client->serverVariables)
                ->map(fn (ServerVariable $variable) => $this->variableParameterOf($variable, $variables[$variable->name], $enums[$variable->name] ?? null))
                ->toArray(),
            ...array_map(fn (string $property) => '        public readonly ?string $'.$property.' = null,', array_values($this->credentialPropertiesOf($client))),
        ];

        $doc = count($variables)
            ? "    /**\n     * @param ?string \$url the whole URL of the API : built from \$".implode(', $', $variables)." when null\n     */\n"
            : '';

        return $this->file($this->configurationName(), ConfigurationElement::class, $doc.Text::interpolate(Text::toFile('
                public function __construct(
            {parameters}
                ) {}
            ', 3), ['parameters' => implode("\n", $parameters)]), new PhpReferences($this->target->namespace, $this->configurationName(), [ConfigurationElement::class]));
    }

    public function renderEnum(string $name, ServerVariable $variable): string
    {
        $cases = Bunch::unzip($this->enumCasesOf($variable))
            ->map(fn (array $pair) => "    case {$pair[1]} = ".var_export((string) $pair[0], true).';')
            ->join("\n");

        return Text::interpolate(Text::toFile('
            <?php

            namespace {namespace};

            /**
             * The values of `{{variable}}` in the server URL. Generated by {generator}, regenerate it rather than editing it
             */
            enum {name}: string
            {
            {cases}
            }
            ', 3), [
            'namespace' => $this->target->namespace,
            'variable' => $variable->name,
            'generator' => $this->generator,
            'name' => $name,
            'cases' => $cases,
        ])."\n";
    }

    /**
     * @return array<string,string> value => case name
     */
    protected function enumCasesOf(ServerVariable $variable): array
    {
        $cases = [];
        foreach ($variable->enum as $value)
            $cases[$value] = DtoNames::unique(DtoNames::className($value), $cases);

        return $cases;
    }

    protected function variableParameterOf(ServerVariable $variable, string $property, ?string $enum): string
    {
        $default = match (true) {
            null === $variable->default => 'null',
            null !== $enum => $enum.'::'.$this->enumCasesOf($variable)[$variable->default],
            default => var_export($variable->default, true),
        };

        return '        public readonly ?'.($enum ?? 'string')." \${$property} = {$default},";
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
        $delimits = Bunch::wrap($client->operations)->any(fn (ClientOperation $operation) => count($operation->queryDelimiters) > 0);

        $blocks = [
            ...($delimits ? [$this->delimitersProperty()] : []),
            $this->baseUrlMethod($client),
            ...Bunch::wrap($client->operations)->map(fn (ClientOperation $operation) => $this->operationMethod($operation, $types))->toArray(),
            $this->sendMethod($references, $authenticates),
            $this->toQueryStringMethod($delimits),
            ...($delimits ? [$this->withDelimitersMethod($references)] : []),
            ...($authenticates ? [$this->credentialsMethod($client)] : []),
            ...($readsLists ? [$this->successfulMethod($references)] : []),
        ];

        return $this->file($this->clientName(), HttpClient::class, implode("\n\n", $blocks), $references);
    }

    protected function baseUrlMethod(DtoClient $client): string
    {
        if (!count($client->serverVariables)) {
            return Text::interpolate(Text::toFile('
                    public function baseURL(): ?string
                    {
                        return {configuration}::resolve()->url;
                    }
                ', 4), ['configuration' => $this->configurationName()]);
        }

        $properties = $this->serverVariablePropertiesOf($client);
        $enums = $this->serverVariableEnumsOf($client);

        $values = Bunch::wrap($client->serverVariables)
            ->map(fn (ServerVariable $variable) => var_export($properties[$variable->name], true).' => $configuration->'.$properties[$variable->name].(isset($enums[$variable->name]) ? '?->value' : ''))
            ->join(', ');

        $replacements = Bunch::wrap($client->serverVariables)
            ->map(fn (ServerVariable $variable) => var_export('{'.$variable->name.'}', true).' => $variables['.var_export($properties[$variable->name], true).']')
            ->join(', ');

        return Text::interpolate(Text::toFile('
                /**
                 * The configured URL, or the server URL of the document built from the configured variables
                 */
                public function baseURL(): ?string
                {
                    $configuration = {configuration}::resolve();
                    if (null !== $configuration->url)
                        return $configuration->url;

                    $variables = [{values}];
                    if (in_array(null, $variables, true))
                        throw new \RuntimeException(\'Cannot build the URL of the API : set [\'.implode(\'], [\', array_keys($variables, null, true)).\'] or url in {configuration}\');

                    return strtr({template}, [{replacements}]);
                }
            ', 3), [
            'configuration' => $this->configurationName(),
            'values' => $values,
            'template' => var_export($client->url, true),
            'replacements' => $replacements,
        ]);
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
        $docs = [];

        // A required argument after an optional one is deprecated : an optional body goes after the required query parameters
        $required = [];
        $optional = [];

        foreach ($operation->pathParameters as $name => $type)
            $required[] = $types->nativeTypeOf($type).' $'.$names['path'][$name];

        if ($request = $operation->request) {
            $argument = $names['request'];

            if ($operation->requestRequired)
                $required[] = $types->nativeTypeOf($request).' $'.$argument;
            else
                $optional[] = $this->optionalArgumentOf($types->nativeTypeOf($request), $argument);

            if ($types->needsDocType($request))
                $docs[] = '@param '.$types->docTypeOf($request).($operation->requestRequired ? '' : '|null').' $'.$argument;
        }

        foreach ($operation->queryParameters as $parameter) {
            $nativeType = $types->nativeTypeOf($parameter->type);

            if ($parameter->required)
                $required[] = $nativeType.' $'.$names['query'][$parameter->key];
            else
                $optional[] = $this->optionalArgumentOf($nativeType, $names['query'][$parameter->key]);
        }

        $arguments = [...$required, ...$optional];

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
    /**
     * A list or a map of classes is converted item by item, and a map is a JSON object even when empty, where
     * json_encode([]) writes a list. An optional body left to null sends no body
     */
    protected function encodedBodyOf(ClientOperation $operation, string $argument): string
    {
        $request = $operation->request;

        $encoded = match (true) {
            $this->isListOfClasses($request) => "array_map(fn (\$item) => \$item->toArray(), {$argument})",
            $request instanceof MapType && $request->value instanceof ClassType => "(object) array_map(fn (\$item) => \$item->toArray(), {$argument})",
            $request instanceof MapType => "(object) {$argument}",
            default => $argument,
        };

        return $operation->requestRequired || $encoded === $argument ? $encoded : "null === {$argument} ? null : {$encoded}";
    }

        protected function optionalArgumentOf(string $nativeType, string $name): string
    {
        return ('mixed' === $nativeType ? 'mixed' : "?{$nativeType}")." \${$name} = null";
    }

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
            $operation->request instanceof ClassType => $argument.($operation->requestRequired ? '->' : '?->').'toArray()',
            default => $this->encodedBodyOf($operation, $argument),
        };

        $schemes = count($operation->security)
            ? '['.implode(', ', array_map(fn (string $scheme) => var_export($scheme, true), $operation->security)).']'
            : null;

        $send = '$this->send('.implode(', ', [
            var_export($operation->method, true),
            $this->pathExpressionOf($operation, $names['path']),
            ...(null !== $query ? ["query: {$query}"] : []),
            ...(null !== $body ? ["body: {$body}"] : []),
            ...(null !== $schemes ? ["schemes: {$schemes}"] : []),
        ]).')';

        if (!count($operation->queryDelimiters))
            return $send;

        $delimiters = Bunch::fromValues(array_keys($operation->queryDelimiters))
            ->map(fn (string $key) => var_export($key, true).' => '.var_export($operation->queryDelimiters[$key], true))
            ->join(', ');

        return "\$this->withDelimiters([{$delimiters}], fn () => {$send})";
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
                    'cookie' => '$cookies[] = '.var_export($authentication->name.'=', true).".{$value};",
                    default => '$headers['.var_export($authentication->name, true).'] = '.($authentication->prefix ? var_export($authentication->prefix, true).'.' : '')."{$value};",
                };

                return '        if (in_array('.var_export($scheme, true).", \$schemes, true) && null !== {$value})\n            {$assignment}";
            })
            ->join("\n\n");

        // A request carries one Cookie header : each cookie scheme adds its cookie to it
        $sendsCookies = Bunch::fromValues($client->authentications)->any(fn (ClientAuthentication $authentication) => 'cookie' === $authentication->location);
        if ($sendsCookies)
            $statements .= "\n\n        if (count(\$cookies))\n            \$headers['Cookie'] = implode('; ', \$cookies);";

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
                    $query = [];{cookies}

            {statements}

                    return [$headers, $query];
                }
            ', 3), [
            'configuration' => $this->configurationName(),
            'cookies' => $sendsCookies ? "\n        \$cookies = [];" : '',
            'statements' => $statements,
        ]);
    }

    protected function toQueryStringMethod(bool $delimits): string
    {
        if (!$delimits) {
            return Text::toFile('
                    /**
                     * A list is sent as one parameter per item (`tags=a&tags=b`) and a boolean as `true` or `false`,
                     * as OpenAPI expects from a query by default
                     *
                     * @param array<string,mixed> $parameters
                     */
                    protected function toQueryString(array $parameters): string
                    {
                        array_walk_recursive($parameters, function (mixed &$value) {
                            if (is_bool($value))
                                $value = $value ? \'true\' : \'false\';
                        });

                        $pairs = [];
                        foreach ($parameters as $name => $value) {
                            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item)
                                $pairs[] = http_build_query([$name => $item], \'\', \'&\');
                        }

                        return implode(\'&\', array_filter($pairs));
                    }
                ', 4);
        }

        return Text::toFile('
                /**
                 * A list is sent as one parameter per item (`tags=a&tags=b`), unless the operation joins its items
                 * (`tags=a,b`), and a boolean as `true` or `false`, as OpenAPI expects from a query
                 *
                 * @param array<string,mixed> $parameters
                 */
                protected function toQueryString(array $parameters): string
                {
                    array_walk_recursive($parameters, function (mixed &$value) {
                        if (is_bool($value))
                            $value = $value ? \'true\' : \'false\';
                    });

                    $pairs = [];
                    foreach ($parameters as $name => $value) {
                        // The delimiter stays literal : percent-encoded, it would be read as part of an item
                        if (isset($this->delimiters[$name]) && is_array($value)) {
                            $pairs[] = rawurlencode($name).\'=\'.implode($this->delimiters[$name], array_map(fn (mixed $item) => rawurlencode((string) $item), $value));
                            continue;
                        }

                        foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item)
                            $pairs[] = http_build_query([$name => $item], \'\', \'&\');
                    }

                    return implode(\'&\', array_filter($pairs));
                }
            ', 3);
    }

    protected function delimitersProperty(): string
    {
        return Text::toFile('
                /** @var array<string,string> query key => what joins the items of its list, for the request being sent */
                protected array $delimiters = [];
            ', 3);
    }

    protected function withDelimitersMethod(PhpReferences $references): string
    {
        return Text::interpolate(Text::toFile('
                /**
                 * Sends a request whose listed query parameters are joined instead of repeated (`explode: false`)
                 *
                 * @param array<string,string> $delimiters query key => what joins the items of its list
                 * @param callable():{response} $send
                 */
                protected function withDelimiters(array $delimiters, callable $send): {response}
                {
                    $this->delimiters = $delimiters;

                    try {
                        return $send();
                    } finally {
                        $this->delimiters = [];
                    }
                }
            ', 3), ['response' => $references->nameOf(Response::class)]);
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
                isset($pathNames[$part]) && $this->isBoolean($operation->pathParameters[$part]) => "(\${$pathNames[$part]} ? 'true' : 'false')",
                isset($pathNames[$part]) => 'rawurlencode((string) $'.$pathNames[$part].')',
                default => var_export('{'.$part.'}', true),
            };
        }

        return implode('.', array_filter($fragments));
    }

    /**
     * Every method of `HttpClient` is taken, whatever its visibility : the generated client extends it
     */
    protected function methodName(ClientOperation $operation): string
    {
        $name = lcfirst($operation->name);

        $taken = array_map(strtolower(...), [
            ...array_map(fn (\ReflectionMethod $method) => $method->name, (new \ReflectionClass(HttpClient::class))->getMethods()),
            ...self::HELPER_METHODS,
        ]);

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

    protected function isBoolean(DtoType $type): bool
    {
        return $type instanceof ScalarType && Scalar::Bool === $type->scalar;
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
        return $this->name ?? preg_replace('/.+\\\/', '', $this->target->namespace);
    }
}
