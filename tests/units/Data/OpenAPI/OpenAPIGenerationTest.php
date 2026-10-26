<?php

namespace Cube\Tests\Units\Data\OpenAPI;

use Cube\Core\Injector;
use Cube\Data\Database\Database;
use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Data\OpenAPI\Configuration\Authentication\BearerToken;
use Cube\Data\OpenAPI\Configuration\Authentication\OpenApiAuthScheme;
use Cube\Data\OpenAPI\OpenAPIGenerator;
use Cube\Data\OpenAPI\OpenAPIConfiguration;
use Cube\Env\Storage;
use Cube\Tests\Units\Data\OpenAPI\Controllers\SampleController;
use Cube\Utils\Path;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class OpenAPIGenerationTest extends TestCase
{
    protected function readFixture(string $fixtureName) {
        $path = Path::join(__DIR__, "Fixtures", $fixtureName);
        if (!file_exists($path))
            throw new InvalidArgumentException("Fixture $path does not exists");

        return file_get_contents($path);
    }

    protected function getSimpleGenerator(?OpenApiAuthScheme $authenticationScheme=null): OpenAPIGenerator
    {
        return new OpenAPIGenerator(
            new OpenAPIConfiguration(
                Storage::getInstance()->path(uniqid() . ".json"),
                'My Test Application',
                '0.1.0',
                $authenticationScheme,
                displayLogs: false
            )
        );
    }

    protected function generateAsArray(Router $router, ?OpenAPIGenerator $generator=null): array
    {
        $generator ??= $this->getSimpleGenerator();
        return json_decode(file_get_contents($generator->generate($router)), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function getStandaloneRouter(): Router
    {
        return new Router(
            new RouterConfiguration(loadControllers: false, loadRoutesFiles: false)
        );
    }

    public function testSimpleGeneration() {
        $router = $this->getStandaloneRouter();
        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADSimple.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADSimple.json fixture");
    }


    public function testSimpleOperationGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/simple-route", [SampleController::class, "simpleRoute"]),
            Route::get("/simple-endpoint", [SampleController::class, "simpleEndpoint"])
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADSimpleOperation.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADSimpleOperation.json fixture");
    }

    public function testAResourceResponseDescribesWhatTheResourceAnswers() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(Route::get("/users", [SampleController::class, "usersAsResource"]));

        $schema = $this->generateAsArray($router)['paths']['/users']['get']['responses'][200]['content']['application/json']['schema'];

        $this->assertEquals('array', $schema['type']);
        $this->assertEquals(['id', 'login'], array_keys($schema['items']['properties']));

        $content = $this->generateAsArray($router)['paths']['/users']['get']['responses'][200]['content']['application/json'];
        $this->assertEquals(['id', 'login'], array_keys($content['example'][0]));
    }

    /** The example model kept random foreign keys, so a resource loading a relation put a real row of the database into the document. */
    public function testAResourceExampleHoldsNoRowOfTheDatabase() {
        $database = new Database();
        $database->exec('CREATE TABLE user_type (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');
        for ($id = 1; $id <= 1000; $id++)
            $database->exec("INSERT INTO user_type (id, label) VALUES ({$id}, 'real-row-{$id}')");

        $router = $this->getStandaloneRouter();
        $router->addRoutes(Route::get("/user", [SampleController::class, "userWithItsType"]));

        $document = Database::withInstance($database, fn () => json_encode($this->generateAsArray($router)));

        $this->assertStringNotContainsString('real-row-', $document);
    }

    public function testAResourceExampleHoldsTheRelationsTheEndpointLoads() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(Route::get("/user", [SampleController::class, "userWithItsLoadedType"]));

        $content = $this->generateAsArray($router)['paths']['/user']['get']['responses'][200]['content']['application/json'];

        $this->assertIsString($content['example']['type']);
        $this->assertEquals('string', $content['schema']['properties']['type']['type']);
    }

    public function testAResourceReadingARelationTheEndpointDoesNotDeclareFails() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(Route::get("/user", [SampleController::class, "userWithoutItsType"]));

        $this->expectException(RuntimeException::class);
        $this->generateAsArray($router);
    }

    public function testAModelSchemaReferencesItsRelationsAsOptional() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(Route::get("/user", [SampleController::class, "userWithItsRelations"]));

        $schemas = $this->generateAsArray($router)['components']['schemas'];

        $this->assertEquals(['oneOf' => [['$ref' => '#/components/schemas/UserType'], ['type' => 'null']]], $schemas['User']['properties']['_type']);
        $this->assertEquals(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ModuleUser']], $schemas['User']['properties']['modules']);
        $this->assertNotContains('_type', $schemas['User']['required']);
        $this->assertArrayHasKey('UserType', $schemas, 'A model only reached through a relation still gets its schema');
        $this->assertArrayHasKey('Module', $schemas);
    }

    public function testAModelResponseRequiresTheRelationsTheEndpointLoads() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(Route::get("/user", [SampleController::class, "userWithItsRelations"]));

        $schema = $this->generateAsArray($router)['paths']['/user']['get']['responses'][200]['content']['application/json']['schema'];

        $this->assertEquals(['allOf' => [
            ['$ref' => '#/components/schemas/User'],
            [
                'required' => ['_type', 'modules'],
                'properties' => ['modules' => ['items' => ['required' => ['_module']]]],
            ],
        ]], $schema);
    }

    /** Every array-callback route was documented, internal ones included. */
    public function testAHiddenRouteIsLeftOut() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/simple-route", [SampleController::class, "simpleRoute"]),
            Route::get("/internal", [SampleController::class, "internalEndpoint"])
        );

        $document = $this->generateAsArray($router);

        $this->assertArrayHasKey('/simple-route', $document['paths']);
        $this->assertArrayNotHasKey('/internal', $document['paths']);
    }


    public function testSlugOperationGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/simple-route/{firstSlug}", [SampleController::class, "simpleSlugEndpoint"]),
            Route::get("/user-route/{user}", [SampleController::class, "modelSlugEndpoint"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADRouteSlugs.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADRouteSlugs.json fixture");
    }

    public function testCustomRequestGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::post("/post-route", [SampleController::class, "postEndpointWithCustomRequest"]),
            Route::patch("/patch-route", [SampleController::class, "patchEndpointWithCustomRequest"]),
            Route::put("/put-route", [SampleController::class, "putEndpointWithCustomRequest"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADCustomRequestFormat.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADCustomRequestFormat.json fixture");
    }

    
    public function testResponseGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/products", [SampleController::class, "endpointReturningAListOfProducts"]),
            Route::get("/product/{product}", [SampleController::class, "endpointReturningAProduct"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADModelResponse.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADModelResponse.json fixture");
    }

    /** A model schema only gave types : its factory now fills an example, the same at every generation. */
    public function testAModelSchemaHoldsAStableExampleFromItsFactory() {
        $generate = function () {
            $router = $this->getStandaloneRouter();
            $router->addRoutes(Route::get("/product/{product}", [SampleController::class, "endpointReturningAProduct"]));

            return $this->generateAsArray($router)['components']['schemas']['Product'];
        };

        $schema = $generate();

        $this->assertEquals('9.99000', $schema['example']['price_dollar']);
        $this->assertEmpty(array_diff(array_keys($schema['example']), array_keys($schema['properties'])));
        $this->assertEquals($schema['example'], $generate()['example']);
    }

    public function testRawResponseGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/raw", [SampleController::class, "endpointReturningARawDataType"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADRawResponse.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADRawResponse.json fixture");
    }

    public function testRawFileResponseGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/raw", [SampleController::class, "endpointReturningAFileDataType"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADRawResponseFile.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADRawResponseFile.json fixture");
    }

    public function testQueryParametersGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/query-route", [SampleController::class, "getEndpointWithQueryRequest"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADQueryParameters.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADQueryParameters.json fixture");
    }

    public function testSlugTypesGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/untyped/{first}/{second}", [SampleController::class, "simpleRoute"]),
            Route::get("/typed/{hex:color}/{uuid:token}/{time:at}", [SampleController::class, "typedSlugsEndpoint"]),
        );

        $generator = $this->getSimpleGenerator();

        $file = $generator->generate($router);
        $fixture = $this->readFixture("OADSlugTypes.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADSlugTypes.json fixture");
    }

    public function testEmptyRawListGeneration() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/empty", [SampleController::class, "endpointReturningAnEmptyList"]),
        );

        $document = $this->generateAsArray($router);
        $schema = $document['paths']['/empty']['get']['responses'][200]['content']['application/json']['schema'];

        $this->assertEquals(['type' => 'array'], $schema['properties']['items']);
    }

    public function testClosureRoutesAreIgnored() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/closure-route", fn() => null),
            Route::get("/simple-route", [SampleController::class, "simpleRoute"]),
        );

        $document = $this->generateAsArray($router);

        $this->assertEquals(['/simple-route'], array_keys($document['paths']));
    }

    public function testGenerationDoesNotAlterRoutes() {
        $router = $this->getStandaloneRouter();
        $router->group('/api', routes: [
            Route::get("/products", [SampleController::class, "simpleRoute"]),
        ]);

        $first = $this->generateAsArray($router);
        $second = $this->generateAsArray($router);

        $this->assertEquals(['/api/products'], array_keys($first['paths']));
        $this->assertEquals($first, $second);
        $this->assertEquals('/api/products', $router->getRoutes()[0]->getPath());
    }

    public function testSecuritySchemeGeneration() {
        $router = $this->getStandaloneRouter();
        $generator = $this->getSimpleGenerator(new BearerToken());

        $document = $this->generateAsArray($router, $generator);

        $this->assertEquals(
            ['BearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']],
            $document['components']['securitySchemes']
        );
        $this->assertEquals([['BearerAuth' => []]], $document['security']);
    }

    public function testValidatedRequestAnswersUnprocessableContent() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::post("/post-route", [SampleController::class, "postEndpointWithCustomRequest"]),
            Route::get("/simple-route", [SampleController::class, "simpleRoute"]),
        );

        $document = $this->generateAsArray($router);

        $this->assertEquals(
            ['$ref' => '#/components/schemas/ValidationErrors'],
            $document['paths']['/post-route']['post']['responses'][422]['content']['application/json']['schema']
        );
        $this->assertArrayHasKey('ValidationErrors', $document['components']['schemas']);
        $this->assertArrayNotHasKey('responses', $document['paths']['/simple-route']['get']);
    }

    public function testModelSlugAnswersNotFound() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/product-model/{product}", [SampleController::class, "endpointReadingAProductSlug"]),
            Route::get("/product-id/{product}", [SampleController::class, "endpointReturningAProduct"]),
            Route::get("/user-union/{user}", [SampleController::class, "modelSlugEndpoint"]),
        );

        $document = $this->generateAsArray($router);

        $this->assertEquals(
            ['description' => 'No item matches the given slug'],
            $document['paths']['/product-model/{product}']['get']['responses'][404]
        );
        $this->assertArrayNotHasKey(404, $document['paths']['/product-id/{product}']['get']['responses']);
        $this->assertArrayNotHasKey('responses', $document['paths']['/user-union/{user}']['get']);
    }

    public function testReturnTypeDescribesTheResponse() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/product", [SampleController::class, "endpointTypedAsAProduct"]),
            Route::get("/optional-product", [SampleController::class, "endpointTypedAsAnOptionalProduct"]),
            Route::get("/void", [SampleController::class, "endpointTypedAsVoid"]),
            Route::get("/response", [SampleController::class, "endpointTypedAsAResponse"]),
            Route::get("/described-product", [SampleController::class, "endpointTypedAndDescribedAsAProduct"]),
        );

        $document = $this->generateAsArray($router);
        $responsesOf = fn (string $path) => $document['paths'][$path]['get']['responses'] ?? [];
        $productResponse = [
            'description' => '',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Product']]],
        ];
        $noContentResponse = ['description' => 'No content'];

        $this->assertEquals([200 => $productResponse], $responsesOf('/product'));
        $this->assertEquals([200 => $productResponse, 204 => $noContentResponse], $responsesOf('/optional-product'));
        $this->assertEquals([204 => $noContentResponse], $responsesOf('/void'));
        $this->assertEquals([], $responsesOf('/response'));
        $this->assertEquals('A product from its attribute', $responsesOf('/described-product')[200]['description']);
        $this->assertArrayHasKey('Product', $document['components']['schemas']);
    }

    public function testUntypedSlugIsTypedByPosition() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/positional/{product}", [SampleController::class, "slugNamedDifferentlyEndpoint"]),
        );

        $document = $this->generateAsArray($router);

        $this->assertEquals(
            ["type" => "integer"],
            $document["paths"]["/positional/{product}"]["get"]["parameters"][0]["schema"]
        );
    }

    public function testDuplicateOperationIdIsRefused() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            new Route("/product/{id}", [SampleController::class, "simpleRoute"], ["PUT", "PATCH"]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Duplicate operationId [simpleRoute] for PUT /product/{id} and PATCH /product/{id}");

        $this->generateAsArray($router);
    }

    /** A Route::any() names no method : it was left out of the document without a word. */
    public function testARouteForAnyMethodIsLeftOutWithAWarning() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::any("/anything", [SampleController::class, "simpleRoute"]),
            Route::get("/simple-route", [SampleController::class, "simpleEndpoint"]),
        );

        $generator = new OpenAPIGenerator(new OpenAPIConfiguration(Storage::getInstance()->path(uniqid() . ".json"), 'My Test Application', '0.1.0'));

        $this->expectOutputRegex('~Warning: ignored route accepting any method.*/anything~');
        $document = $this->generateAsArray($router, $generator);

        $this->assertEquals(['/simple-route'], array_keys($document['paths']));
    }

    /** Only an object of rules was read : a getRules() returning a single list rule gave no request body. */
    public function testASingleRequestRuleDescribesTheBody() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::post("/ids", [SampleController::class, "postEndpointWithListRequest"]),
        );

        $operation = $this->generateAsArray($router)['paths']['/ids']['post'];

        $this->assertEquals(
            ['type' => 'array', 'items' => ['type' => 'integer']],
            $operation['requestBody']['content']['application/json']['schema']
        );
        $this->assertArrayHasKey(422, $operation['responses']);
    }

    /** inArray() wrote a `string` type over the rule's own, so a list of integers was documented as strings. */
    public function testAnEnumKeepsTheRuleType() {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/products", [SampleController::class, "getEndpointWithEnumRequest"]),
        );

        $parameter = $this->generateAsArray($router)['paths']['/products']['get']['parameters'][0];

        $this->assertEquals(['type' => 'integer', 'enum' => [10, 25, 50]], $parameter['schema']);
    }

    /** The file was checked as given, so a path relative to the project root only worked from the project root. */
    public function testARawResponseFileIsRelativeToTheProjectRoot() {
        $workingDirectory = getcwd();
        chdir(sys_get_temp_dir());

        try {
            $response = new RawResponse(file: 'tests/units/Data/OpenAPI/Fixtures/RawResponseSource.json');
        } finally {
            chdir($workingDirectory);
        }

        $this->assertEquals(json_decode($this->readFixture('RawResponseSource.json'), true), $response->data);
    }

    protected function getOutputShapesRouter(): Router
    {
        $router = $this->getStandaloneRouter();
        $router->addRoutes(
            Route::get("/album", [SampleController::class, "endpointReturningAnAlbum"]),
            Route::post("/any-value", [SampleController::class, "postEndpointWithAnyValueRequest"]),
            Route::get("/untyped-raw", [SampleController::class, "endpointReturningUntypedRawData"]),
            Route::get("/albums", [SampleController::class, "endpointReturningAPageOfAlbums"]),
            Route::post("/chunk", [SampleController::class, "endpointReceivingAChunk"]),
            Route::get("/track", [SampleController::class, "endpointStreamingATrack"]),
            Route::get("/cover", [SampleController::class, "endpointWithSeveralResponses"]),
            Route::get("/directory", [SampleController::class, "endpointReturningAUserDirectory"]),
        );

        return $router;
    }

    public function testOutputShapesGeneration() {
        $file = $this->getSimpleGenerator()->generate($this->getOutputShapesRouter());
        $fixture = $this->readFixture("OADOutputShapes.json");

        $this->assertEquals($fixture, file_get_contents($file), "File $file does not match OADOutputShapes.json fixture");
    }

    /** The model schema came from toObjectParam(), the shape of a write : no auto-incremented key, and fields with a default were optional. */
    public function testAModelSchemaDescribesTheModelAsRead() {
        $schema = $this->generateAsArray($this->getOutputShapesRouter())['components']['schemas']['Album'];

        $this->assertEquals(['id', 'name', 'last_update', 'released_at'], $schema['required']);
        $this->assertEquals(['type' => 'integer'], $schema['properties']['id']);
        $this->assertEquals(['type' => ['string', 'null'], 'format' => 'date'], $schema['properties']['released_at']);
    }

    /** Datetimes were announced as RFC 3339 date-time, while Cube reads and writes them as `Y-m-d H:i:s`. */
    public function testADatetimeIsDescribedByItsPattern() {
        $schema = $this->generateAsArray($this->getOutputShapesRouter())['components']['schemas']['Album'];

        $this->assertEquals(['type' => 'string', 'pattern' => Route::SLUG_FORMATS['datetime']], $schema['properties']['last_update']);
        $this->assertMatchesRegularExpression('~^' . Route::SLUG_FORMATS['datetime'] . '$~', $schema['example']['last_update']);
    }

    /** An untyped schema was encoded as an empty JSON array, which is not a schema. */
    public function testAnUntypedSchemaIsAnEmptyObject() {
        $file = $this->getSimpleGenerator()->generate($this->getOutputShapesRouter());
        $paths = json_decode(file_get_contents($file), flags: JSON_THROW_ON_ERROR)->paths;

        $body = $paths->{'/any-value'}->post->requestBody->content->{'application/json'}->schema->properties;
        $this->assertEquals(new stdClass(), $body->payload);
        $this->assertEquals(new stdClass(), $body->tags->items);
        $this->assertEquals(new stdClass(), $body->options->properties);

        $raw = $paths->{'/untyped-raw'}->get->responses->{'200'}->content->{'application/json'}->schema->properties;
        $this->assertEquals(new stdClass(), $raw->nested->properties->value);
        $this->assertEquals(new stdClass(), $raw->list->items);
    }

    public function testARawResponseReferencesAModelByItsClass() {
        $document = $this->generateAsArray($this->getOutputShapesRouter());
        $schema = $document['paths']['/albums']['get']['responses'][200]['content']['application/json']['schema'];

        $this->assertEquals(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Album']], $schema['properties']['items']);
        $this->assertArrayHasKey('Album', $document['components']['schemas']);
    }

    public function testARawBodyIsBinary() {
        $requestBody = $this->generateAsArray($this->getOutputShapesRouter())['paths']['/chunk']['post']['requestBody'];

        $this->assertEquals([
            'required' => true,
            'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]],
            'description' => 'A chunk of the uploaded file',
        ], $requestBody);
    }

    public function testANonJsonRawResponseWithoutDataIsBinary() {
        $responses = $this->generateAsArray($this->getOutputShapesRouter())['paths']['/track']['get']['responses'];

        $this->assertEquals(['audio/mpeg' => ['schema' => ['type' => 'string', 'format' => 'binary']]], $responses[200]['content']);
    }

    public function testAJsonRawResponseStillNeedsData() {
        $this->expectException(InvalidArgumentException::class);
        new RawResponse(mimeType: 'application/problem+json');
    }

    /** A 204 could only come from the return type, and ModelResponse / RawResponse could not be repeated. */
    public function testResponsesCombineOnOneEndpoint() {
        $responses = $this->generateAsArray($this->getOutputShapesRouter())['paths']['/cover']['get']['responses'];

        $this->assertEqualsCanonicalizing([200, 204, 409, 410], array_keys($responses));
        $this->assertEquals(['description' => 'The album has no cover'], $responses[204]);
        $this->assertEquals(['error' => ['type' => 'string']], $responses[410]['content']['application/json']['schema']['properties']);
    }

    public function testAnObjectResponseShapesTheResourcesItHolds() {
        $document = $this->generateAsArray($this->getOutputShapesRouter());
        $properties = $document['paths']['/directory']['get']['responses'][200]['content']['application/json']['schema']['properties'];

        $this->assertEquals(['id', 'login'], array_keys($properties['owner']['properties']));
        $this->assertEquals('array', $properties['members']['type']);
        $this->assertEquals(['id', 'login'], array_keys($properties['members']['items']['properties']));
        $this->assertEquals(['$ref' => '#/components/schemas/UserType'], $properties['type']);
        $this->assertEquals(['type' => 'integer'], $properties['total']);
        $this->assertEquals(['type' => 'string'], $properties['admins']['items']['properties']['type'], 'The relation named by the ResourceSchema is made on the example model');
        $this->assertArrayHasKey('UserType', $document['components']['schemas']);
    }
}
