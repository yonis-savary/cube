<?php

namespace Cube\Tests\Units\Web\Servers;

use Cube\Event\Events;
use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use Cube\Web\Router\Router;
use Cube\Web\Router\RouterConfiguration;
use Cube\Web\Servers\Events\SocketServerSetup;
use Cube\Web\Servers\UnixSocketServer;
use PHPUnit\Framework\TestCase;
use React\Http\Io\BufferedBody;
use React\Http\Io\UploadedFile;
use React\Http\Message\ServerRequest;
use RuntimeException;

class UnixSocketServerTest extends TestCase
{
    protected function tearDown(): void
    {
        Counter::removeInstance();
    }

    public function testTheRouterIsDeclaredBySetupListeners()
    {
        $events = new Events();
        SocketServerSetup::on(fn (SocketServerSetup $event) => $event->router->addRoutes(
            Route::get('/status', fn () => Response::ok('up'))
        ), $events);

        $router = UnixSocketServer::createRouter();
        $events->asGlobalInstance(function () use (&$router) {
            $socketServer = new UnixSocketServer("/somepath/server.sock", $router);
            $socketServer->setup();
        });

        $this->assertCount(1, $router->getRoutes());
    }

    public function testTheSetupRouterDiscoversNothing()
    {
        $router = null;
        (new Events())->asGlobalInstance(function () use (&$router) {
            $router = UnixSocketServer::createRouter();
        });

        $response = $router->route(new Request('GET', '/ping'));

        $this->assertEquals(StatusCode::NOT_FOUND, $response->getStatusCode());
    }

    public function testARequestIsRoutedAndAnsweredAsPsr()
    {
        $server = $this->newServer(Route::post('/products', fn () => Response::json(['created' => true], StatusCode::CREATED)));

        $response = $server->handle(new ServerRequest('POST', 'http://localhost/products'));

        $this->assertEquals(StatusCode::CREATED, $response->getStatusCode());
        $this->assertEquals('{"created":true}', (string) $response->getBody());
    }

    public function testAThrowingRouteAnswersAnInternalServerError()
    {
        $server = $this->newServer(Route::get('/broken', fn () => throw new RuntimeException('boom')));

        $response = $server->handle(new ServerRequest('GET', 'http://localhost/broken'));

        $this->assertEquals(StatusCode::INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function testTheServerKeepsAnsweringAfterAFailedRequest()
    {
        $server = $this->newServer(
            Route::get('/broken', fn () => throw new RuntimeException('boom')),
            Route::get('/status', fn () => Response::ok('up')),
        );

        $server->handle(new ServerRequest('GET', 'http://localhost/broken'));
        $response = $server->handle(new ServerRequest('GET', 'http://localhost/status'));

        $this->assertEquals('up', (string) $response->getBody());
    }

    public function testComponentsDoNotLeakBetweenRequests()
    {
        $server = $this->newServer(Route::get('/count', function () {
            Counter::getInstance()->tag = 'touched';
            return Response::ok();
        }));

        $server->handle(new ServerRequest('GET', 'http://localhost/count'));

        $this->assertFalse(Counter::hasInstance());
    }

    public function testUnmovedUploadsAreRemovedAfterTheRequest()
    {
        $tempNames = [];
        $server = $this->newServer(Route::post('/documents', function (Request $request) use (&$tempNames) {
            $tempNames[] = $request->upload('invoice')->tempName;
            return Response::noContent();
        }));

        $server->handle((new ServerRequest('POST', 'http://localhost/documents'))->withUploadedFiles([
            'invoice' => new UploadedFile(new BufferedBody('invoice content'), 15, UPLOAD_ERR_OK, 'invoice.pdf', 'application/pdf'),
        ]));

        $this->assertCount(1, $tempNames);
        $this->assertFileDoesNotExist($tempNames[0]);
    }

    protected function newServer(Route ...$routes): UnixSocketServer
    {
        $router = new Router(new RouterConfiguration(false, false, false));
        $router->addRoutes(...$routes);

        return new UnixSocketServer('/tmp/unused.sock', $router);
    }
}
