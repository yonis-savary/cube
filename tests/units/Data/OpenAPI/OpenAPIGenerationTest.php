<?php

namespace Cube\Tests\Units\Data\OpenAPI;

use Cube\Data\OpenAPI\OpenAPIGenerator;
use Cube\Data\OpenAPI\OpenAPIGeneratorConfiguration;
use Cube\Tests\Units\Data\OpenAPI\Controllers\SampleController;
use Cube\Utils\Path;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OpenAPIGenerationTest extends TestCase
{
    protected function readFixture(string $fixtureName) {
        $path = Path::join(__DIR__, "Fixtures", $fixtureName);
        if (!file_exists($path))
            throw new InvalidArgumentException("Fixture $path does not exists");

        return file_get_contents($path);
    }

    protected function getSimpleGenerator(): OpenAPIGenerator
    {
        return new OpenAPIGenerator(
            new OpenAPIGeneratorConfiguration(uniqid() . ".json", 'My Test Application', '0.1.0')
        );
    }

    protected function getStandaloneRouter(): Router
    {
        return new Router(new RouterConfiguration(loadControllers: false, loadRoutesFiles: false));
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
            Route::patch("/patch-route", [SampleController::class, "postEndpointWithCustomRequest"]),
            Route::put("/put-route", [SampleController::class, "postEndpointWithCustomRequest"]),
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

    

}