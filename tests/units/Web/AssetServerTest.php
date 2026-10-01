<?php

namespace Cube\Tests\Units\Web;

use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use Cube\Tests\Units\Web\Classes\TemporaryAssetServer;
use Cube\Web\Http\Request;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AssetServerTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('asset-server-test-');
        $this->storage->write('app.css', 'body { color: red; }');
        $this->storage->write('app.js', 'console.log("hello")');

        TemporaryAssetServer::servingFrom($this->storage);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    /**
     * Slug values are keyed by name, never by position : reading index 0 always answered null,
     * so the asset server could not serve a single file.
     */
    public function testTheRequestedAssetIsServed()
    {
        $response = $this->route('/assets/app.css');

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
        $this->assertEquals('body { color: red; }', $response->getBody());
    }

    public function testAnUnknownAssetAnswersNotFound()
    {
        $response = $this->route('/assets/unknown.css');

        $this->assertEquals(StatusCode::NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('unknown.css', $response->getBody());
    }

    public function testTheRouteCanBeNamedAnything()
    {
        $response = $this->route('/static/app.js', new TemporaryAssetServer('/static/{asset}'));

        $this->assertEquals('console.log("hello")', $response->getBody());
    }

    /** The 404 echoed the requested name with no Content-Type, so the browser rendered it as HTML. */
    public function testAnUnknownAssetNameIsNotRenderedAsHtml()
    {
        $response = $this->route('/assets/%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E');

        $contentType = $response->getHeader('content-type') ?? 'text/html';
        $rendersMarkup = str_contains($contentType, 'html') && str_contains($response->getBody(), '<img');

        $this->assertFalse($rendersMarkup, "The 404 is served as {$contentType} with the requested markup inside");
    }

    protected function route(string $path, ?TemporaryAssetServer $server = null)
    {
        $server ??= new TemporaryAssetServer();

        $router = new Router(new RouterConfiguration(false, false, false, [$server], [], '/'));

        return $router->route(new Request('GET', $path));
    }
}
