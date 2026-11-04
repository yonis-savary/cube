<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Cube\Data\Bunch;
use Cube\Data\DataToObject\Generator\ClientWriter;
use Cube\Data\DataToObject\Generator\Sources\OpenApiSource;
use Cube\Env\Configuration;
use Cube\Utils\Path;
use Cube\Web\Http\HttpClient;
use Cube\Web\Http\HttpMockServer;
use Cube\Web\Http\MockServers;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class ClientWriterTest extends TestCase
{
    use GeneratesClasses;

    /** @var Request[] */
    protected array $received = [];

    protected function source(): OpenApiSource
    {
        return new OpenApiSource(json_decode(file_get_contents(Path::join(__DIR__, 'Fixtures', 'openapi-billing.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    protected function petstore(): OpenApiSource
    {
        return new OpenApiSource(json_decode(file_get_contents(Path::join(__DIR__, 'Fixtures', 'openapi-petstore.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    protected function helpdesk(): OpenApiSource
    {
        return new OpenApiSource(json_decode(file_get_contents(Path::join(__DIR__, 'Fixtures', 'openapi-helpdesk.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,string> $credentials
     */
    protected function asHelpdeskUser(array $credentials, callable $callback): mixed
    {
        return (new Configuration(new ($this->generatedClass('ApiConfiguration'))(...$credentials)))->asGlobalInstance($callback);
    }

    protected function generateClient(): object
    {
        return $this->clientOf(
            $this->source(),
            Route::post('/products', $this->answer(Response::json(['id' => 7, 'label' => 'Pen', 'category' => ['id' => 2]]))),
            Route::get('/products', $this->answer(Response::json([['id' => 7, 'label' => 'Pen', 'category' => ['id' => 2]]]))),
            Route::get('/invoices/{int:id}', $this->answer(Response::json(['created_at' => '2026-01-01', 'number' => 'F-1', 'status' => 'paid', 'lines' => []]))),
        );
    }

    /**
     * Generates the client of `$source`, served by `$routes`
     */
    protected function clientOf(OpenApiSource $source, Route ...$routes): object
    {
        $this->assertGeneratedFilesAreValid([...$this->writer()->write($source->toClasses()), ...$this->clientWriter()->write($source->toClient())]);
        $this->autoloadGeneratedClasses();

        $client = new ($this->generatedClass('ApiClient'))();
        MockServers::getInstance()->set($client::class, HttpMockServer::fromRoutes(...$routes));

        return $client;
    }

    /**
     * A route callback that records the request it receives in `$received`
     */
    protected function answer(Response $response): \Closure
    {
        return function (Request $request) use ($response) {
            $this->received[] = $request;

            return $response;
        };
    }

    protected function tearDown(): void
    {
        MockServers::removeInstance();

        if ($this->generatedClassesLoader)
            spl_autoload_unregister($this->generatedClassesLoader);

        $this->tearDownTemporaryStorage();
    }

    protected function withToken(string $token, callable $callback): mixed
    {
        return (new Configuration(new ($this->generatedClass('ApiConfiguration'))(bearerAuth: $token)))->asGlobalInstance($callback);
    }

    public function testTheClientAndItsConfigurationAreNamedAfterTheNamespace()
    {
        $client = $this->source()->toClient();

        $this->assertStringContainsString('class ApiClient extends HttpClient', $this->clientWriter()->renderClient($client));
        $this->assertStringContainsString('public readonly ?string $url = null,', $this->clientWriter()->renderConfiguration($client), 'Built from {region}');
        $this->assertStringContainsString("public readonly ?string \$region = 'eu',", $this->clientWriter()->renderConfiguration($client));
        $this->assertStringContainsString('public readonly ?string $bearerAuth = null,', $this->clientWriter()->renderConfiguration($client));
    }

    public function testAnOperationNamesItsPathParametersAndQueryArguments()
    {
        $source = $this->clientWriter()->renderClient($this->source()->toClient());

        $this->assertStringContainsString('public function getInvoicesId(int $id): GetInvoicesIdResponse', $source);
        $this->assertStringContainsString('public function createProduct(CreateProductRequest $request, ?bool $dryRun = null): CreateProductResponse', $source);
        $this->assertStringContainsString('public function listProducts(?ListProductsRequest $request = null): array', $source);
        $this->assertStringContainsString('@return list<Product>', $source);
    }

    public function testABodyIsSentAsJsonWithTheKeysOfTheDocument()
    {
        $client = $this->generateClient();

        $request = new ($this->generatedClass('Http\\Requests\\CreateProductRequest'))('Pen');
        $request->unitPrice = 1.5;

        $product = $client->createProduct($request, dryRun: true);

        $this->assertInstanceOf($this->generatedClass('Http\\Responses\\CreateProductResponse'), $product);
        $this->assertEquals(7, $product->id);

        $sent = $this->received[0];
        $this->assertEquals(['label' => 'Pen', 'unit-price' => 1.5], json_decode($sent->getBody(), true));
        $this->assertEquals(['dry_run' => 1], $sent->get());
    }

    public function testTheConfigurationGivesTheUrlAndTheCredentials()
    {
        $client = $this->generateClient();

        $this->assertEquals('https://eu.billing.example.com/v1', $client->baseURL());

        $this->withToken('secret', fn () => $client->getInvoicesId(12));
        $this->assertEquals('Bearer secret', $this->received[0]->getHeader('Authorization'));

        $client->getInvoicesId(12);
        $this->assertNull($this->received[1]->getHeader('Authorization'), 'No credential is configured');
    }

    public function testAnOperationWithAnEmptySecurityRequirementSendsNoCredential()
    {
        $client = $this->generateClient();

        $this->withToken('secret', fn () => $client->listProducts());

        $this->assertNull($this->received[0]->getHeader('Authorization'));
    }

    public function testAQueryRequestIsSentInTheUrlAndAListIsHydrated()
    {
        $client = $this->generateClient();

        $request = new ($this->generatedClass('Http\\Requests\\ListProductsRequest'))();
        $request->labelContains = 'pe';

        $products = $client->listProducts($request);

        $this->assertInstanceOf($this->generatedClass('Refs\\Output\\Product'), $products[0]);
        $this->assertEquals(['label-contains' => 'pe'], $this->received[0]->get());
    }

    public function testPathParametersAreEncodedIntoThePath()
    {
        $client = $this->generateClient();

        $invoice = $client->getInvoicesId(12);

        $this->assertEquals('F-1', $invoice->number);
        $this->assertEquals('/invoices/12', $this->received[0]->getPath());
    }

    public function testArgumentsGivingTheSameNameAreNumbered()
    {
        $string = ['type' => 'string'];
        $source = new OpenApiSource(['openapi' => '3.1.0', 'servers' => [['url' => 'https://helpdesk.example.com']], 'paths' => [
            '/requests/{request}/notes/{id}' => ['put' => [
                'operationId' => 'updateRequest',
                'parameters' => [
                    ['name' => 'request', 'in' => 'path', 'required' => true, 'schema' => $string],
                    ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['name' => 'id', 'in' => 'query', 'schema' => $string],
                    ['name' => 'dry_run', 'in' => 'query', 'schema' => $string],
                    ['name' => 'dryRun', 'in' => 'query', 'schema' => $string],
                ],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['note'], 'properties' => ['note' => $string]]]]],
                'responses' => ['204' => ['description' => '']],
            ]],
        ]]);

        $this->assertStringContainsString(
            'public function updateRequest(string $request, int $id, UpdateRequestRequest $request1, ?string $id1 = null, ?string $dryRun = null, ?string $dryRun1 = null): Response',
            $this->clientWriter()->renderClient($source->toClient()),
        );

        $client = $this->clientOf($source, Route::put('/requests/{ticket}/notes/{int:note}', $this->answer(Response::noContent())));
        $client->updateRequest('R-1', 3, new ($this->generatedClass('Http\\Requests\\UpdateRequestRequest'))('urgent'), id1: 'x', dryRun: 'yes', dryRun1: 'no');

        $sent = $this->received[0];
        $this->assertEquals('/requests/R-1/notes/3', $sent->getPath());
        $this->assertEquals(['id' => 'x', 'dry_run' => 'yes', 'dryRun' => 'no'], $sent->get());
        $this->assertEquals(['note' => 'urgent'], json_decode($sent->getBody(), true));
    }

    public function testSchemesGivingTheSameCredentialPropertyAreNumbered()
    {
        $source = new OpenApiSource([
            'openapi' => '3.1.0',
            'servers' => [['url' => 'https://helpdesk.example.com']],
            'security' => [['api_key' => [], 'apiKey' => [], 'url' => []]],
            'paths' => ['/ping' => ['get' => ['operationId' => 'ping', 'responses' => ['204' => ['description' => '']]]]],
            'components' => ['securitySchemes' => [
                'api_key' => ['type' => 'apiKey', 'in' => 'query', 'name' => 'key'],
                'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Key'],
                'url' => ['type' => 'http', 'scheme' => 'bearer'],
            ]],
        ]);

        $configuration = $this->clientWriter()->renderConfiguration($source->toClient());
        $this->assertStringContainsString('public readonly ?string $apiKey = null,', $configuration);
        $this->assertStringContainsString('public readonly ?string $apiKey1 = null,', $configuration);
        $this->assertStringContainsString('public readonly ?string $url1 = null,', $configuration, '$url is the base URL of the API');

        $client = $this->clientOf($source, Route::get('/ping', $this->answer(Response::noContent())));
        (new Configuration(new ($this->generatedClass('ApiConfiguration'))(apiKey: 'query-key', apiKey1: 'header-key', url1: 'token')))
            ->asGlobalInstance(fn () => $client->ping());

        $sent = $this->received[0];
        $this->assertEquals(['key' => 'query-key'], $sent->get());
        $this->assertEquals('header-key', $sent->getHeader('X-Key'));
        $this->assertEquals('Bearer token', $sent->getHeader('Authorization'));
    }

    public function testThePetstoreFindsPetsByARequiredQueryAndHydratesTheirTags()
    {
        $client = $this->clientOf($this->petstore(), Route::get('/pet/findByStatus', $this->answer(Response::json([
            ['id' => 10, 'name' => 'doggie', 'photoUrls' => [], 'tags' => [['id' => 3, 'name' => 'friendly']], 'status' => 'available'],
        ]))));

        $pets = $client->findPetsByStatus(new ($this->generatedClass('Http\\Requests\\FindPetsByStatusRequest'))('available'));

        $this->assertEquals(['status' => 'available'], $this->received[0]->get());
        $this->assertInstanceOf($this->generatedClass('Refs\\Output\\Tag'), $pets[0]->tags[0]);
        $this->assertEquals('friendly', $pets[0]->tags[0]->name);
    }

    public function testThePetstoreSendsEveryConfiguredCredentialAnOperationAccepts()
    {
        $client = $this->clientOf($this->petstore(), Route::get('/pet/{int:id}', $this->answer(Response::json(['id' => 10, 'name' => 'doggie', 'photoUrls' => []]))));
        $configuration = fn (array $credentials) => new Configuration(new ($this->generatedClass('ApiConfiguration'))(...$credentials));

        $pet = $configuration(['apiKey' => 'special-key'])->asGlobalInstance(fn () => $client->getPetById(10));
        $configuration(['petstoreAuth' => 'oauth-token'])->asGlobalInstance(fn () => $client->getPetById(10));

        $this->assertEquals('doggie', $pet->name);
        $this->assertEquals('special-key', $this->received[0]->getHeader('api_key'));
        $this->assertNull($this->received[0]->getHeader('Authorization'));
        $this->assertEquals('Bearer oauth-token', $this->received[1]->getHeader('Authorization'));
    }

    public function testThePetstoreSendsAListOfUsersAsAJsonArray()
    {
        $client = $this->clientOf($this->petstore(), Route::post('/user/createWithList', $this->answer(Response::json(['id' => 1, 'username' => 'theUser']))));

        $user = new ($this->generatedClass('Refs\\Input\\User'))();
        $user->username = 'theUser';
        $user->firstName = 'John';

        $created = $client->createUsersWithListInput([$user]);

        $this->assertEquals([['username' => 'theUser', 'firstName' => 'John']], json_decode($this->received[0]->getBody(), true));
        $this->assertEquals('theUser', $created->username);
    }

    public function testThePetstoreInventoryIsAMapReturnedAsTheRawResponse()
    {
        $client = $this->clientOf($this->petstore(), Route::get('/store/inventory', $this->answer(Response::json(['available' => 12, 'sold' => 3]))));

        $this->assertEquals(['available' => 12, 'sold' => 3], $client->getInventory()->getJSON());
    }

    public function testAListInTheQueryIsSentAsOneParameterPerItem()
    {
        $client = $this->clientOf($this->petstore(), Route::get('/pet/findByTags', $this->answer(Response::json([]))));

        $client->findPetsByTags(new ($this->generatedClass('Http\\Requests\\FindPetsByTagsRequest'))(['friendly', 'young']));

        $this->assertEquals(
            'https://petstore3.swagger.io/api/v3/pet/findByTags?tags=friendly&tags=young',
            curl_getinfo($client->toCurlHandle($this->received[0]), CURLINFO_EFFECTIVE_URL),
        );
    }

    public function testABooleanInTheQueryIsSentAsTrueOrFalse()
    {
        $client = $this->clientOf($this->petstore());

        $request = new Request('GET', '/user/login', ['username' => 'the user', 'remember' => true, 'filter' => ['status' => 'sold', 'archived' => false], 'page' => null, 'tags' => []]);

        $this->assertEquals(
            'https://petstore3.swagger.io/api/v3/user/login?username=the+user&remember=true&filter%5Bstatus%5D=sold&filter%5Barchived%5D=false',
            curl_getinfo($client->toCurlHandle($request), CURLINFO_EFFECTIVE_URL),
        );
    }

    public function testTheClientIsNamedAfterTheGivenName()
    {
        $writer = new ClientWriter($this->target(), 'test', 'Petstore');

        $this->assertEquals('PetstoreClient', $writer->clientName());
        $this->assertEquals('PetstoreConfiguration', $writer->configurationName());
        $this->assertStringContainsString('return PetstoreConfiguration::resolve()->url;', $writer->renderClient($this->petstore()->toClient()));
    }

    /**
     * An operation named after a protected method of HttpClient (`path`) overrode it with another
     * signature : a fatal error when the class loads, that `php -l` does not see. A magic method gives no
     * clash, its name losing its underscores (`__construct` gives `construct`)
     */
    public function testAnOperationNamedAfterAnHttpClientMethodGetsASuffix()
    {
        $names = Bunch::wrap((new \ReflectionClass(HttpClient::class))->getMethods())
            ->map(fn (\ReflectionMethod $method) => $method->name)
            ->filter(fn (string $name) => !str_starts_with($name, '__'))
            ->push('send', 'credentials', 'successful')
            ->toArray();

        $paths = [];
        foreach ($names as $name)
            $paths["/{$name}"] = ['get' => ['operationId' => $name, 'responses' => ['204' => ['description' => '']]]];

        $source = new OpenApiSource(['openapi' => '3.1.0', 'paths' => $paths]);
        $client = $this->clientWriter()->write($source->toClient())[1];

        $rendered = file_get_contents($client);
        foreach ($names as $name)
            $this->assertStringContainsString("public function {$name}Operation(): Response", $rendered);

        $load = new Process([PHP_BINARY, '-r', 'require '.var_export(Path::relative('vendor/autoload.php'), true).'; require '.var_export($client, true).';']);
        $load->run();

        $this->assertTrue($load->isSuccessful(), $load->getOutput().$load->getErrorOutput());
    }

    public function testAScalarBodyComesBetweenThePathAndTheQueryArguments()
    {
        $source = $this->helpdesk();

        $this->assertStringContainsString(
            'public function addComment(int $ticketId, string $body, bool $notify, ?string $locale = null): AddCommentResponse',
            $this->clientWriter()->renderClient($source->toClient()),
        );

        $client = $this->clientOf($source, Route::post('/tickets/{int:ticket}/comments', $this->answer(Response::json(['id' => 9, 'body' => 'Fixed in 2.1'], 201))));
        $comment = $client->addComment(4, 'Fixed in 2.1', notify: true);

        $sent = $this->received[0];
        $this->assertEquals('"Fixed in 2.1"', $sent->getBody(), 'A JSON string');
        $this->assertEquals(['notify' => 1], $sent->get(), 'A null optional query parameter is left out');
        $this->assertEquals(9, $comment->id);
    }

    public function testAListOrAMapOfScalarsIsSentAsIs()
    {
        $client = $this->clientOf(
            $this->helpdesk(),
            Route::put('/tickets/{int:ticket}/tags', $this->answer(Response::noContent())),
            Route::patch('/tickets/{int:ticket}/fields', $this->answer(Response::noContent())),
        );

        $client->replaceTags(4, ['urgent', 'billing']);
        $client->updateCustomFields(4, ['priority' => 'high']);
        $client->updateCustomFields(4, []);

        $this->assertEquals('["urgent","billing"]', $this->received[0]->getBody());
        $this->assertEquals('{"priority":"high"}', $this->received[1]->getBody());
        $this->assertEquals('{}', $this->received[2]->getBody(), 'An empty map is still a JSON object');
    }

    public function testAMapOfObjectsIsSentWithTheKeysOfTheDocument()
    {
        $client = $this->clientOf($this->helpdesk(), Route::put('/tickets/{int:ticket}/assignees', $this->answer(Response::noContent())));

        $assignment = new ($this->generatedClass('Refs\\Input\\Assignment'))(12);
        $assignment->note = 'Back on monday';

        $client->assignTicket(4, ['billing' => $assignment, 'support' => new ($this->generatedClass('Refs\\Input\\Assignment'))(7)]);
        $client->assignTicket(4, []);

        $this->assertEquals(
            ['billing' => ['agent-id' => 12, 'note' => 'Back on monday'], 'support' => ['agent-id' => 7]],
            json_decode($this->received[0]->getBody(), true),
        );
        $this->assertEquals('{}', $this->received[1]->getBody());
    }

    public function testCookieBasicAndOpenIdConnectCredentialsAreSent()
    {
        $client = $this->clientOf(
            $this->helpdesk(),
            Route::get('/tickets', $this->answer(Response::json([]))),
            Route::post('/tickets/{int:ticket}/comments', $this->answer(Response::json(['id' => 9, 'body' => 'Noted'], 201))),
            Route::put('/tickets/{int:ticket}/tags', $this->answer(Response::noContent())),
        );

        $this->asHelpdeskUser(
            ['session' => 'f3a9c1', 'basic' => base64_encode('agent@acme.example:secret'), 'sso' => 'id-token'],
            function () use ($client) {
                $client->listTickets();
                $client->addComment(4, 'Noted', notify: false);
                $client->replaceTags(4, []);
            },
        );

        [$listing, $commenting, $tagging] = $this->received;
        $this->assertEquals('helpdesk_session=f3a9c1', $listing->getHeader('Cookie'));
        $this->assertEquals('Basic '.base64_encode('agent@acme.example:secret'), $commenting->getHeader('Authorization'), 'The configured value is sent as is, already encoded');
        $this->assertEquals('Bearer id-token', $tagging->getHeader('Authorization'), 'openIdConnect flows are not run : the configured token is a bearer token');
    }

    public function testAScalarResponseIsReturnedAsTheResponse()
    {
        $client = $this->clientOf($this->helpdesk(), Route::get('/tickets/{int:ticket}/subject', $this->answer(Response::json('Printer on fire'))));

        $this->assertEquals('Printer on fire', $client->getTicketSubject(4)->getJSON());
    }

    public function testAListResponseThatIsNotSuccessfulThrows()
    {
        $client = $this->clientOf($this->helpdesk(), Route::get('/tickets', $this->answer(Response::internalServerError())));

        $this->expectExceptionMessage('Could not read the response : the API answered 500');

        $client->listTickets();
    }

    public function testAnObjectResponseThatIsNotSuccessfulThrows()
    {
        $client = $this->clientOf($this->helpdesk(), Route::post('/tickets/{int:ticket}/comments', $this->answer(Response::notFound())));

        $this->expectExceptionMessage('response code is 404');

        $client->addComment(404, 'Anyone there ?', notify: false);
    }

    /** An empty schema (`{}`, any JSON) was taken for no body : the method had no argument to send it. */
    public function testABodyOfAnyJsonIsAMixedArgument()
    {
        $source = $this->helpdesk();

        $this->assertStringContainsString('public function replaceMetadata(int $ticketId, mixed $body): Response', $this->clientWriter()->renderClient($source->toClient()));

        $client = $this->clientOf($source, Route::put('/tickets/{int:ticket}/metadata', $this->answer(Response::noContent())));
        $client->replaceMetadata(4, ['source' => 'email', 'attempts' => [1, 2]]);

        $this->assertEquals('{"source":"email","attempts":[1,2]}', $this->received[0]->getBody());
    }

    public function testServerVariablesAreConfiguredAndAnEnumGivesTheirValues()
    {
        $source = $this->helpdesk();
        $files = $this->clientWriter()->write($source->toClient());

        $this->assertContains($this->storage->path('ApiVersion.php'), $files);
        $this->assertStringContainsString("public readonly ?ApiVersion \$version = ApiVersion::V2,", file_get_contents($this->storage->path('ApiConfiguration.php')));
        $this->assertStringContainsString("public readonly ?string \$tenant = 'acme',", file_get_contents($this->storage->path('ApiConfiguration.php')), 'No enum : a string');

        $client = $this->clientOf($source);
        $configured = fn (array $values) => (new Configuration(new ($this->generatedClass('ApiConfiguration'))(...$values)))->asGlobalInstance(fn () => $client->baseURL());

        $this->assertEquals('https://acme.helpdesk.example.com/api/v2', $client->baseURL(), 'The defaults of the document');
        $this->assertEquals('https://globex.helpdesk.example.com/api/v1', $configured(['tenant' => 'globex', 'version' => $this->generatedClass('ApiVersion')::V1]));
        $this->assertEquals('https://staging.helpdesk.example.com/api', $configured(['url' => 'https://staging.helpdesk.example.com/api']), 'The url overrides the variables');
    }

    public function testAServerVariableWithoutDefaultMustBeConfigured()
    {
        $source = new OpenApiSource(['openapi' => '3.0.3', 'servers' => [['url' => 'https://{tenant}.helpdesk.example.com/api', 'variables' => [
            'tenant' => ['enum' => ['acme', 'globex']],
        ]]], 'paths' => []]);

        $client = $this->clientOf($source);
        $this->assertEquals(
            'https://globex.helpdesk.example.com/api',
            (new Configuration(new ($this->generatedClass('ApiConfiguration'))(tenant: $this->generatedClass('ApiTenant')::Globex)))->asGlobalInstance(fn () => $client->baseURL()),
        );

        $this->expectExceptionMessage('Cannot build the URL of the API : set [tenant] or url in ApiConfiguration');

        $client->baseURL();
    }

    public function testABodyIsOptionalUnlessTheDocumentRequiresIt()
    {
        $helpdesk = $this->clientWriter()->renderClient($this->helpdesk()->toClient());
        $this->assertStringContainsString('public function replaceMetadata(int $ticketId, mixed $body): Response', $helpdesk, 'required: true');
        $this->assertStringContainsString("     * @param array<string,string>|null \$body\n     */\n    public function updateCustomFields(int \$ticketId, ?array \$body = null): Response", $helpdesk);

        $petstore = $this->clientWriter()->renderClient($this->petstore()->toClient());
        $this->assertStringContainsString('public function addPet(AddPetRequest $request): AddPetResponse', $petstore, 'required: true');
        $this->assertStringContainsString('public function placeOrder(?PlaceOrderRequest $request = null): PlaceOrderResponse', $petstore, 'required is false by default');

        $client = $this->clientOf($this->helpdesk(), Route::patch('/tickets/{int:ticket}/fields', $this->answer(Response::noContent())));
        $client->updateCustomFields(4);

        $this->assertEquals('', $this->received[0]->getBody(), 'A body left to null is not sent');
        $this->assertNull($this->received[0]->getHeader('content-type'));
    }

    public function testAnOptionalBodyOfObjectsIsLeftOutWhenNull()
    {
        $client = $this->clientOf(
            $this->petstore(),
            Route::post('/store/order', $this->answer(Response::json(['id' => 1]))),
            Route::post('/user/createWithList', $this->answer(Response::json(['id' => 1]))),
        );

        $client->placeOrder();
        $client->createUsersWithListInput();

        $this->assertEquals('', $this->received[0]->getBody());
        $this->assertEquals('', $this->received[1]->getBody());
    }

    public function testAnOptionalBodyComesAfterTheRequiredQueryParameters()
    {
        $string = ['type' => 'string'];
        $source = new OpenApiSource(['openapi' => '3.1.0', 'paths' => ['/exports' => ['post' => [
            'operationId' => 'createExport',
            'parameters' => [
                ['name' => 'locale', 'in' => 'query', 'schema' => $string],
                ['name' => 'format', 'in' => 'query', 'required' => true, 'schema' => $string],
            ],
            'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['columns' => ['type' => 'array', 'items' => $string]]]]]],
            'responses' => ['204' => ['description' => '']],
        ]]]]);

        $client = $this->clientWriter()->write($source->toClient())[1];
        $this->writer()->write($source->toClasses());

        $this->assertStringContainsString('public function createExport(string $format, ?CreateExportRequest $request = null, ?string $locale = null): Response', file_get_contents($client));

        // Linting does not report a deprecated signature, loading the class does
        $load = new Process([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-r', 'require '.var_export(Path::relative('vendor/autoload.php'), true).'; require '.var_export($client, true).';']);
        $load->run();

        $this->assertTrue($load->isSuccessful(), $load->getErrorOutput());
        $this->assertEquals('', $load->getErrorOutput());
    }

    public function testABooleanInThePathIsSentAsTrueOrFalse()
    {
        $source = new OpenApiSource(['openapi' => '3.1.0', 'paths' => ['/features/{enabled}' => ['get' => [
            'operationId' => 'listFeatures',
            'parameters' => [['name' => 'enabled', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'boolean']]],
            'responses' => ['204' => ['description' => '']],
        ]]]]);

        $client = $this->clientOf($source, Route::get('/features/{flag}', $this->answer(Response::noContent())));
        $client->listFeatures(true);
        $client->listFeatures(false);

        $this->assertEquals('/features/true', $this->received[0]->getPath());
        $this->assertEquals('/features/false', $this->received[1]->getPath());
    }

    /** Each cookie scheme set the Cookie header, the last one overwriting the others. */
    public function testEveryConfiguredCookieGoesInTheSameHeader()
    {
        $client = $this->clientOf($this->helpdesk(), Route::get('/tickets', $this->answer(Response::json([]))));

        $this->asHelpdeskUser(['session' => 'f3a9c1', 'csrf' => '8b2e'], fn () => $client->listTickets());
        $this->asHelpdeskUser(['csrf' => '8b2e'], fn () => $client->listTickets());

        $this->assertEquals('helpdesk_session=f3a9c1; csrf_token=8b2e', $this->received[0]->getHeader('Cookie'));
        $this->assertEquals('csrf_token=8b2e', $this->received[1]->getHeader('Cookie'), 'Only the configured ones');
        $this->assertStringNotContainsString('$cookies', $this->clientWriter()->renderClient($this->petstore()->toClient()), 'No cookie scheme, no cookie to gather');
    }
}

