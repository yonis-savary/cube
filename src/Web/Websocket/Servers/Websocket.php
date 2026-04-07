<?php

namespace Cube\Web\Websocket\Servers;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Http\HttpServer as ReactHttpServer;
use React\Socket\SocketServer;

/**
 * This simple component is used to launch the websocket service, which is made of
 * - a pure websocket server
 * - an http server used to transmit messages from cube to the websocket server
 */
class Websocket
{
    use Component;

    public function __construct(
        protected Logger $logger,
        protected WebsocketConfiguration $configuration,
        protected WebsocketRouter $router
    )
    {
    }

    public function serve()
    {
        $loop = Loop::get();

        $webSocketOrigin = $this->configuration->getWebsocketOrigin();
        $webSocketServer = new IoServer(
            new HttpServer(new WsServer($this->router)),
            new SocketServer($webSocketOrigin, [], $loop),
            $loop
        );

        $this->logger->info("WebSocket server ready at $webSocketOrigin");

        $httpOrigin = $this->configuration->getHttpOrigin();
        $httpSocket = new SocketServer($httpOrigin, [], $loop);
        $httpServer = new ReactHttpServer($this->router->getHttpServerCallback());
        $httpServer->listen($httpSocket);

        $this->logger->info("HTTP server listening at $httpOrigin");

        $loop->run();
    }
}
