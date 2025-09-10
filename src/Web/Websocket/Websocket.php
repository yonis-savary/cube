<?php

namespace Cube\Web\Websocket;

use Cube\Core\Component;
use Cube\Http\StatusCode;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Http\HttpServer as ReactHttpServer;
use React\Http\Message\Response as ReactResponse;
use React\Socket\SocketServer;

/**
 * This simple component is used to launch the websocket service, which is made of
 * - a pure websocket server
 * - an http server used to transmit messages from cube to the websocket server
 */
class Websocket
{
    protected WebsocketConfiguration $configuration;

    use Component;

    public function getHttpServerCallback(SubscriberTypeSocket $application)
    {
        return function (ServerRequestInterface $request) use (&$application) {
            $method = $request->getMethod();

            $path = $request->getUri()->getPath();
            $event = trim($path, '/ ');

            $logger = $this->configuration->logger;
            $logger->info("{method} {path}", ['method' => $method, 'path' => $path]);

            $sampleResponse = ReactResponse::plaintext("OK")
                ->withStatus(StatusCode::OK)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, ' . $this->configuration->apiKeyHeader)
            ;

            if ($method === 'OPTIONS') {
                return $sampleResponse;
            }

            $apiKey = $request->getHeaderLine($this->configuration->apiKeyHeader);
            if ($apiKey !== $this->configuration->apiKey) {
                return $sampleResponse->withStatus(StatusCode::UNAUTHORIZED);
            }

            if ($method !== 'POST') {
                return $sampleResponse->withStatus(StatusCode::BAD_REQUEST);
            }

            $application->dispatch($event, (string)$request->getBody());

            return $sampleResponse;
        };
    }

    public function serve(?WebsocketConfiguration $configuration = null)
    {
        $this->configuration = $configuration ?? WebsocketConfiguration::resolve();
        $logger = $this->configuration->logger;

        $loop = Loop::get();

        $wsApp = new SubscriberTypeSocket($this->configuration);

        $webSocketServerAddress = $this->configuration->websocketAddress;
        $webSocketServer = new IoServer(
            new HttpServer(new WsServer($wsApp)),
            new SocketServer($webSocketServerAddress, [], $loop),
            $loop
        );

        $logger->info("WebSocket server ready at ws://{address}", ['address' => $webSocketServerAddress]);

        $httpSocketAddress = $this->configuration->websocketInternalAddress;
        $httpSocket = new SocketServer($httpSocketAddress, [], $loop);
        $httpServer = new ReactHttpServer($this->getHttpServerCallback($wsApp));
        $httpServer->listen($httpSocket);

        $logger->info("HTTP server listening at http://{address}", ['address' => $httpSocketAddress]);

        $loop->run();
    }
}
