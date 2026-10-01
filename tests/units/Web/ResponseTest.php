<?php

namespace Cube\Tests\Units\Web;

use Cube\Env\Storage;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use Cube\Web\Http\Configuration\CORSConfiguration;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ResponseTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('response-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    public function testAResponseWithoutABodyIsEmpty()
    {
        $response = new Response();

        $this->assertEquals(StatusCode::NO_CONTENT, $response->getStatusCode());
        $this->assertEquals('', $response->getBody());
    }

    public function testFactoriesCarryTheirStatusCode()
    {
        $factories = [
            'ok' => StatusCode::OK,
            'created' => StatusCode::CREATED,
            'noContent' => StatusCode::NO_CONTENT,
            'partialContent' => StatusCode::PARTIAL_CONTENT,
            'badRequest' => StatusCode::BAD_REQUEST,
            'unauthorized' => StatusCode::UNAUTHORIZED,
            'forbidden' => StatusCode::FORBIDDEN,
            'notFound' => StatusCode::NOT_FOUND,
            'unprocessableContent' => StatusCode::UNPROCESSABLE_CONTENT,
            'internalServerError' => StatusCode::INTERNAL_SERVER_ERROR,
            'variantAlsoNegotiates' => StatusCode::VARIANT_ALSO_NEGOTIATES,
        ];

        foreach ($factories as $factory => $code) {
            $this->assertEquals($code, Response::{$factory}()->getStatusCode(), "Response::{$factory}()");
        }
    }

    public function testTextIsSentAsPlainText()
    {
        $response = Response::text('<b>not markup</b>', StatusCode::NOT_FOUND);

        $this->assertEquals(StatusCode::NOT_FOUND, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeader('content-type'));
        $this->assertEquals('<b>not markup</b>', $response->getBody());
    }

    public function testIsOkCoversTheWholeTwoHundredRange()
    {
        $this->assertTrue(Response::ok()->isOk());
        $this->assertTrue(Response::created()->isOk());
        $this->assertTrue(Response::noContent()->isOk());

        $this->assertFalse(Response::movedPermanently()->isOk());
        $this->assertFalse(Response::notFound()->isOk());
        $this->assertFalse(Response::internalServerError()->isOk());
    }

    public function testRedirectionsCarryTheirTargetInALocationHeader()
    {
        $this->assertEquals('/login', Response::temporaryRedirect('/login')->getHeader('location'));
        $this->assertEquals('/login', Response::permanentRedirect('/login')->getHeader('location'));
    }

    public function testJsonSetsItsContentType()
    {
        $response = Response::json(['name' => 'screen']);

        $this->assertEquals('application/json', $response->getHeader('content-type'));
        $this->assertEquals(['name' => 'screen'], $response->getJSON());
    }

    public function testHtmlSetsItsContentType()
    {
        $response = Response::html('<p>hello</p>');

        $this->assertEquals('text/html', $response->getHeader('content-type'));
        $this->assertEquals('<p>hello</p>', $response->getBody());
    }

    public function testHeadersAreCaseInsensitive()
    {
        $response = (new Response())->withHeaders(['X-Custom-Header' => 'value']);

        $this->assertEquals('value', $response->getHeader('x-custom-header'));
        $this->assertEquals('value', $response->getHeader('X-CUSTOM-HEADER'));
    }

    public function testClientCachingIsExpressedInSeconds()
    {
        $this->assertEquals('max-age=3600', (new Response())->withClientCaching(3600)->getHeader('cache-control'));
    }

    public function testCorsHeadersComeFromTheConfiguration()
    {
        $configuration = new CORSConfiguration('https://example.org', 'Content-Type', 'false', 600);

        $response = (new Response())->withCORSHeaders(['GET', 'POST'], $configuration);

        $this->assertEquals('https://example.org', $response->getHeader('access-control-allow-origin'));
        $this->assertEquals('GET, POST', $response->getHeader('access-control-allow-methods'));
        $this->assertEquals('Content-Type', $response->getHeader('access-control-allow-headers'));
        $this->assertEquals('600', $response->getHeader('access-control-max-age'));
    }

    public function testCorsAllowsEveryMethodWhenNoneIsGiven()
    {
        $this->assertEquals('*', (new Response())->withCORSHeaders()->getHeader('access-control-allow-methods'));
    }

    public function testFileAnswersTheFileContent()
    {
        $this->storage->write('report.txt', 'yearly report');

        $response = Response::file($this->storage->path('report.txt'));

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals('yearly report', $response->getBody());
        $this->assertEquals('text/plain', $response->getHeader('content-type'));
    }

    public function testDownloadAsksTheBrowserToSaveTheFile()
    {
        $this->storage->write('report.txt', 'yearly report');

        $response = Response::download($this->storage->path('report.txt'));

        $this->assertEquals('application/octet-stream', $response->getHeader('content-type'));
        $this->assertEquals('attachment; filename=report.txt', $response->getHeader('content-disposition'));
        $this->assertEquals('yearly report', $response->getBody());
    }

    public function testAResponseCallbackReplacesTheBody()
    {
        $response = (new Response(StatusCode::OK, 'ignored'))
            ->withResponseCallback(function () { echo 'from the callback'; });

        $this->assertEquals('from the callback', $response->getBody());
    }

    /**
     * `server/Public/index.php` closes every request with `$response->logSelf()` : losing the
     * method turns each one into a 500, and only a test going through a real entry point
     * noticed the last time.
     */
    public function testAResponseCanLogItself()
    {
        $logger = new SpyLogger();

        Response::json(['name' => 'screen'])->logSelf($logger);

        $this->assertEquals(['info'], $logger->levels());
        $this->assertEquals('{code} {content-type}', $logger->records[0][1]);
    }

    public function testToObjectRefusesAFailedResponse()
    {
        $this->expectException(\RuntimeException::class);

        Response::notFound('{}')->toObject(Storage::class);
    }
}
