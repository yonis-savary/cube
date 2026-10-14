<?php

namespace Cube\Tests\Units\Data\OpenAPI;

use Cube\Core\Injector;
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
}
