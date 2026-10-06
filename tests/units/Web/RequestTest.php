<?php 

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use Cube\Tests\Units\Models\Module;
use Cube\Tests\Units\Web\Examples\PriceRequest;
use Cube\Web\Http\Request;
use Cube\Web\Http\Rules\Param;
use Cube\Web\Http\Response;
use Cube\Web\Http\Upload;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\Http\Io\BufferedBody;
use React\Http\Io\UploadedFile;
use React\Http\Message\ServerRequest;

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

    public function testARequestCanLogItselfAndGivesItselfBack()
    {
        $logger = new SpyLogger();
        $request = new Request('GET', '/product');

        $this->assertSame($request, $request->logSelf($logger));
        $this->assertEquals(['info'], $logger->levels());
        $this->assertEquals('{method} {path}', $logger->records[0][1]);
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

    public function testTheConstructorTrimsTheTrailingSlash()
    {
        $this->assertEquals('/products', (new Request('GET', '/products/'))->getPath());
        $this->assertEquals('/', (new Request('GET', '/'))->getPath());
    }

    public function testTheConstructorNormalizesScalarStrings()
    {
        $request = new Request('POST', '/', ['archived' => 'false', 'deleted' => 'null'], ['visible' => 'on']);

        $this->assertSame(['archived' => false, 'deleted' => null], $request->get());
        $this->assertSame(['visible' => true], $request->post());
    }

    public function testAJsonBodyKeepsItsOwnTypes()
    {
        $request = new Request('POST', '/', [], [], ['Content-Type' => 'application/json'], [], '{"status":"off"}');

        $this->assertSame(['status' => 'off'], $request->post());
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

    public function testFromPsrRequestCarriesEveryPart()
    {
        $psrRequest = (new ServerRequest('POST', 'http://localhost/products?page=2', ['X-Token' => 'abc'], 'body', '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
            ->withParsedBody(['name' => 'screen'])
            ->withCookieParams(['session' => 'xyz']);

        $request = Request::fromPsrRequest($psrRequest);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/products', $request->getPath());
        $this->assertEquals(['page' => '2'], $request->get());
        $this->assertEquals(['name' => 'screen'], $request->post());
        $this->assertEquals('abc', $request->getHeader('x-token'));
        $this->assertEquals('body', $request->getBody());
        $this->assertEquals('127.0.0.1', $request->getIp());
        $this->assertEquals(['session' => 'xyz'], $request->getCookies());
    }

    public function testFromPsrRequestKeepsTheSubclass()
    {
        $request = PriceRequest::fromPsrRequest(new ServerRequest('GET', 'http://localhost/?price=50'));

        $this->assertInstanceOf(PriceRequest::class, $request);
        $this->assertEquals('50', $request->param('price'));
    }

    public function testFromPsrRequestTrimsTheTrailingSlash()
    {
        $this->assertEquals('/products', Request::fromPsrRequest(new ServerRequest('GET', 'http://localhost/products/?page=2'))->getPath());
        $this->assertEquals('/', Request::fromPsrRequest(new ServerRequest('GET', 'http://localhost/'))->getPath());
    }

    public function testFromPsrRequestNormalizesScalarStrings()
    {
        $request = Request::fromPsrRequest(
            (new ServerRequest('POST', 'http://localhost/?archived=false&deleted=null'))
                ->withParsedBody(['visible' => 'on'])
        );

        $this->assertSame(['archived' => false, 'deleted' => null], $request->get());
        $this->assertSame(['visible' => true], $request->post());
    }

    public function testFromPsrRequestJoinsRepeatedHeaders()
    {
        $request = Request::fromPsrRequest(new ServerRequest('GET', 'http://localhost/', ['Accept' => ['text/html', 'application/json']]));

        $this->assertEquals('text/html, application/json', $request->getHeader('accept'));
    }

    public function testFromPsrRequestDecodesAJsonBody()
    {
        $request = Request::fromPsrRequest(new ServerRequest('POST', 'http://localhost/', ['Content-Type' => 'application/json'], '{"name":"screen"}'));

        $this->assertEquals(['name' => 'screen'], $request->post());
    }

    public function testARequestWithoutRemoteAddressHasNoIp()
    {
        $this->assertNull(Request::fromPsrRequest(new ServerRequest('GET', 'http://localhost/'))->getIp());
    }

    public function testFromPsrRequestWritesUploadsToTemporaryFiles()
    {
        $request = Request::fromPsrRequest(
            (new ServerRequest('POST', 'http://localhost/'))->withUploadedFiles([
                'invoice' => new UploadedFile(new BufferedBody('invoice content'), 15, UPLOAD_ERR_OK, 'invoice.pdf', 'application/pdf'),
                'photos' => [
                    new UploadedFile(new BufferedBody('first'), 5, UPLOAD_ERR_OK, 'first.png', 'image/png'),
                    new UploadedFile(new BufferedBody('second'), 6, UPLOAD_ERR_OK, 'second.png', 'image/png'),
                ],
            ])
        );

        try {
            $this->assertEquals('invoice content', file_get_contents($request->upload('invoice')->tempName));

            $this->assertEquals(['first', 'second'], array_map(
                fn (Upload $upload) => file_get_contents($upload->tempName),
                $request->uploads('photos')
            ));
        } finally {
            foreach ($request->getUploads() as $upload)
                unlink($upload->tempName);
        }
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