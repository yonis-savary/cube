<?php

namespace Cube\Web\Websocket\Servers;

use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Throwable;
use Cube\Web\Http\StatusCode;
use Cube\Web\Websocket\Channel\Channel;
use React\Http\Io\BufferedBody;
use React\Http\Io\HttpBodyStream;
use React\Http\Message\Response as ReactResponse;

use function Cube\debug;

/**
 * This class is a Ratcher socket used to make socket communication through a subscription design pattern
 */
class WebsocketRouter implements MessageComponentInterface
{
    private Logger $logger;

    /**
     * @var array<class-string<Channel>,Channel> $channels
     */
    protected array $channels = [];

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->channels = Bunch::fromExtends(Channel::class)->zip(fn(Channel $instance) => [$instance::class, $instance]);

        foreach ($this->channels as $class => $instance) {
            $this->logger->info(" - Registering channel $class...");
        }
    }

    public function onOpen(ConnectionInterface $connection)
    {
        $path = $connection->httpRequest->getUri()->getPath();
        $this->logger->info("New connection : {id} ({path})", ['id' => $connection->resourceId, "path" => $path]);

        foreach ($this->channels as $channel) {
            if ($channel->match($path)) {
                $channel->subscribe($path, $connection);
                return;
            }
        }
        $this->logger->info("No channel found");
    }

    public function onClose(ConnectionInterface $connection)
    {
        foreach ($this->channels as $channel) {
            if (!$channel->unsubscribe($connection)) 
                continue;

            $this->logger->info("Connection closed ({id})", ["id" => $connection->resourceId]);
            return;
        }
    }

    public function onError(ConnectionInterface $conn, Throwable $e)
    {
        $this->logger->error("Error on websocket ({id})", ['id' => $conn->resourceId]);
        $this->logger->logThrowable($e);
        $conn->close();
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->logger->info(static::class . ": this class does not support the onMessage() method");
    }

    public function broadcast($message)
    {
        $this->logger->info(static::class . ": this class does not support the broadcast() method");
    }

    public function dispatch(ServerRequestInterface $request, ReactResponse $response): ReactResponse
    {
        $path = $request->getUri()->getPath();
        $body = json_decode($request->getBody(), true);

        if (!($class = $body["__class"] ?? false)) {
            $this->logger->error("Recieved Invalid message : __class missing");
            return $response->withBody(new BufferedBody("__class missing"))->withStatus(StatusCode::BAD_REQUEST);
        }

        if (!($channel = $this->channels[$class] ?? false)) {
            $this->logger->error("Recieved Invalid message : invalid __class");
            return $response->withBody(new BufferedBody("invalid __class"))->withStatus(StatusCode::UNPROCESSABLE_CONTENT);
        }

        $this->logger->info('Dispatch {path}', ['path' => $path]);
        $channel->dispatch($path, $body);
        return $response;
    }


    public function getHttpServerCallback()
    {
        return function (ServerRequestInterface $request) {
            $method = $request->getMethod();
            $path = $request->getUri()->getPath();


            debug(
                "GOT REQUEST",
                $method,
                $path,
                join(";", $request->getHeader("Content-Type")),
                $request->getBody()
            );

            $logger = Logger::getInstance();
            $logger->info("{method} {path}", ['method' => $method, 'path' => $path]);

            $response = ReactResponse::plaintext("OK")
                ->withStatus(StatusCode::OK)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', '*')
            ;

            if ($method === 'OPTIONS') {
                return $response;
            }

            if ($method !== 'POST') {
                return $response->withStatus(StatusCode::BAD_REQUEST);
            }

            if (!str_contains(join(";", $request->getHeader("Content-Type")), "application/json")) {
                return $response->withStatus(StatusCode::BAD_REQUEST);
            }

            return $this->dispatch($request, $response);
        };
    }
}