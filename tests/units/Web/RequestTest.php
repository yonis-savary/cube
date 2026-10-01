<?php 

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Web\Examples\PriceRequest;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Response;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    use TestMultipleDrivers;

    public static function fakeCallback(Request $request, Module $module) {
        /** @var Module $module */
        $module = $request->getSlugObject("module");

        return Response::json($module->label);
    }

    public function testOnlyReturnsSpecifiedKeys()
    {
        $request = new Request('POST', '/', [], ['name' => 'screen', 'price_dollar' => 10, 'extra' => 'ignored']);

        $result = $request->only(['name', 'price_dollar']);

        $this->assertEquals(['name' => 'screen', 'price_dollar' => 10], $result);
        $this->assertArrayNotHasKey('extra', $result);
    }

    public function testOnlySilentlyIgnoresMissingKeys()
    {
        $request = new Request('POST', '/', [], ['name' => 'screen']);

        $result = $request->only(['name', 'price_dollar']);

        $this->assertEquals(['name' => 'screen'], $result);
        $this->assertArrayNotHasKey('price_dollar', $result);
    }

    public function testOnlyWithValidated()
    {
        $baseRequest = new Request('GET', '/', ['price' => '50'], []);
        $request = PriceRequest::fromRequest($baseRequest); // validated() will parse the price to int

        $unvalidatedOnly = $request->only(['price'], false);
        $validatedOnly = $request->only(['price'], true);

        $this->assertTrue($unvalidatedOnly['price'] === '50');
        $this->assertTrue($validatedOnly['price'] === 50);
    }

    public function testParamReadsGetThenPost()
    {
        $request = new Request('POST', '/', ['page' => '2'], ['page' => '9', 'name' => 'screen']);

        $this->assertEquals('2', $request->param('page'));
        $this->assertEquals('screen', $request->param('name'));
        $this->assertNull($request->param('unknown'));
        $this->assertEquals('fallback', $request->param('unknown', 'fallback'));
    }

    /**
     * param() used to look into the raw body first, and a body is a string : asking for "0"
     * answered its first byte instead of the parameter of that name.
     */
    public function testParamDoesNotReadIntoTheRawBody()
    {
        $request = new Request('POST', '/', [], ['0' => 'the real value'], [], [], 'abcdef');

        $this->assertEquals('the real value', $request->param('0'));
    }

    public function testAJsonBodyIsDecodedIntoPost()
    {
        $request = new Request(
            'POST',
            '/',
            [],
            [],
            ['content-type' => 'application/json'],
            [],
            json_encode(['name' => 'screen', 'price' => 10])
        );

        $this->assertEquals(['name' => 'screen', 'price' => 10], $request->post());
        $this->assertEquals('screen', $request->param('name'));
    }

    /**
     * A malformed body is something a client got wrong, not something the framework should
     * blow up on : the request stays usable and the rules answer for it.
     */
    public function testAMalformedJsonBodyLeavesTheRequestUsable()
    {
        $request = new Request('POST', '/', [], [], ['content-type' => 'application/json'], [], '{not json');

        $this->assertEquals([], $request->post());
        $this->assertEquals('{not json', $request->getBody());
    }

    public function testQueryStringIsStrippedFromThePath()
    {
        $this->assertEquals('/products', (new Request('GET', '/products?page=2'))->getPath());
    }

    /** fromGlobals() trimmed the trailing slash before stripping the query, so `/products/?page=2` kept it. */
    public function testATrailingSlashBeforeTheQueryStringIsTrimmed()
    {
        $originalUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/products/?page=2';

        try {
            $this->assertEquals('/products', Request::fromGlobals()->getPath());
        } finally {
            $_SERVER['REQUEST_URI'] = $originalUri;
        }
    }

    public function testAnEmptyQueryStringIsNotPartOfThePath()
    {
        $this->assertEquals('/products', (new Request('GET', '/products?'))->getPath());
    }

    public function testAllMergesGetAndPost()
    {
        $request = new Request('POST', '/', ['shared' => 'from get'], ['shared' => 'from post', 'only' => 'post']);

        $this->assertEquals(['shared' => 'from get', 'only' => 'post'], $request->all());
        $this->assertEquals(['shared' => 'from post', 'only' => 'post'], $request->all(false));
    }

    public function testFromRequestKeepsTheSubclass()
    {
        $request = PriceRequest::fromRequest(new Request('GET', '/', ['price' => '50']));

        $this->assertInstanceOf(PriceRequest::class, $request);
        $this->assertEquals('50', $request->param('price'));
    }

    public function testFromRequestCarriesEveryPart()
    {
        $source = new Request('POST', '/products', ['page' => '2'], ['name' => 'screen'], ['x-token' => 'abc'], [], 'body', '127.0.0.1', ['session' => 'xyz']);

        $copy = Request::fromRequest($source);

        $this->assertEquals('POST', $copy->getMethod());
        $this->assertEquals('/products', $copy->getPath());
        $this->assertEquals(['page' => '2'], $copy->get());
        $this->assertEquals(['name' => 'screen'], $copy->post());
        $this->assertEquals('abc', $copy->getHeader('x-token'));
        $this->assertEquals('body', $copy->getBody());
        $this->assertEquals('127.0.0.1', $copy->getIp());
        $this->assertEquals(['session' => 'xyz'], $copy->getCookies());
    }

    public function testValidatedAcceptsAnExplicitValidator()
    {
        $request = new Request('GET', '/', ['price' => '50', 'extra' => 'ignored']);

        $validated = $request->validated(validator: Param::object(['price' => Param::integer()]));

        $this->assertSame(['price' => 50], $validated);
    }

    public function testValidatedRefusesAKeyItDoesNotHold()
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Request('GET', '/', ['price' => '50']))->validated('unknown', Param::object(['price' => Param::integer()]));
    }

    public function testARequestWithoutRulesAcceptsAnything()
    {
        $request = new Request('GET', '/', ['anything' => 'goes']);

        $this->assertTrue($request->isValid());
        $this->assertEquals(['anything' => 'goes'], $request->validated());
    }

    public function testAnInvalidRequestReportsItsErrors()
    {
        $request = PriceRequest::fromRequest(new Request('GET', '/', ['price' => 'not a number']));

        $this->assertFalse($request->isValid());
        $this->assertArrayHasKey('price', $request->validate()->getErrors());
    }

    #[ DataProvider('getDatabases') ]
    public function test_parameters_binding(Database $database) {
        Database::withInstance($database, function(){
            $router = new Router(new RouterConfiguration());
            $router->addRoutes(
                Route::get("/{module}", [self::class, "fakeCallback"])
            );

            $response = $router->route(
                new Request("GET", "/1")
            );

            $this->assertEquals("product", $response->getJSON());
        });
    }
}