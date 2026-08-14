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

    protected function route(string $path, ?TemporaryAssetServer $server = null)
    {
        $server ??= new TemporaryAssetServer();

        $router = new Router(new RouterConfiguration(false, false, false, [$server], [], '/'));

        return $router->route(new Request('GET', $path));
    }
}
