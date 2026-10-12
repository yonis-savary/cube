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
use RuntimeException;

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
        if ($this->configuration->exposesBroadcastWithoutSecret())
            throw new RuntimeException("The broadcast server would listen on {$this->configuration->getHttpOrigin()} without a broadcastSecret : anyone reaching it could emit on every channel");

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

        if ('-1' === ini_get('memory_limit')) {
            $this->logger->warning('memory_limit is -1 : a single client can send a message big enough to exhaust the memory of the websocket process');
        }

        if (null === $this->configuration->broadcastSecret) {
            $this->logger->warning('No broadcastSecret configured : anyone reaching {origin} can broadcast', ['origin' => $httpOrigin]);
        }

        $loop->run();
    }
}
