<?php

namespace Cube\Web\Websocket\Servers;

use Cube\Core\Component;
use Cube\Data\Database\Database;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Env\Logger\Logger;
use Cube\Web\Websocket\Broadcast\Broadcast;
use Cube\Web\Websocket\Broadcast\BroadcastConfiguration;
use Cube\Web\Websocket\Broadcast\LocalBroadcast;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer as ReactHttpServer;
use React\Socket\SocketServer;

/**
 * This simple component is used to launch the websocket service, which is made of
 * - a pure websocket server
 * - an http server used to transmit messages from cube to the websocket server.
 */
class Websocket
{
    use Component;

    public function __construct(
        protected Logger $logger,
        protected WebsocketConfiguration $configuration,
        protected WebsocketRouter $router
    ) {}

    protected function keepDatabaseAlive(LoopInterface $loop, ?DatabaseConfiguration $configuration = null): void
    {
        $configuration ??= DatabaseConfiguration::resolve();
        if (!$interval = $configuration->keepAliveInterval)
            return;
        // A server that never touched the database has no connection to keep : opening one would be a side effect
        $loop->addPeriodicTimer($interval, function () {
            if (Database::hasInstance())
                Database::getInstance()->keepAlive($this->logger);
        });
    }

    public function serve()
    {
        if ($this->configuration->exposesBroadcastWithoutSecret())
            throw new \RuntimeException("The broadcast server would listen on {$this->configuration->getHttpOrigin()} without a broadcastSecret : anyone reaching it could emit on every channel");

        $loop = Loop::get();

        $webSocketOrigin = $this->configuration->getWebsocketOrigin();
        $webSocketServer = new IoServer(
            new HttpServer(new WsServer($this->router)),
            new SocketServer($webSocketOrigin, [], $loop),
            $loop
        );

        $this->logger->info("WebSocket server ready at {$webSocketOrigin}");

        $httpOrigin = $this->configuration->getHttpOrigin();
        $httpSocket = new SocketServer($httpOrigin, [], $loop);
        $httpServer = new ReactHttpServer($this->router->getHttpServerCallback());
        $httpServer->listen($httpSocket);

        $this->logger->info("HTTP server listening at {$httpOrigin}");

        if ('-1' === ini_get('memory_limit')) {
            $this->logger->warning('memory_limit is -1 : a single client can send a message big enough to exhaust the memory of the websocket process');
        }

        if (null === $this->configuration->broadcastSecret) {
            $this->logger->warning('No broadcastSecret configured : anyone reaching {origin} can broadcast', ['origin' => $httpOrigin]);
        }

        $this->keepDatabaseAlive($loop);

        Broadcast::withInstance(
            new LocalBroadcast(BroadcastConfiguration::resolve(), $this->router),
            fn () => $loop->run()
        );
    }
}
