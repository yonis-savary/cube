<?php

namespace Cube\Web\Websocket;

use Cube\Env\Logger\Logger;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;
use Throwable;

/**
 * This class is a Ratcher socket used to make socket communication through a subscription design pattern
 */
class SubscriberTypeSocket implements MessageComponentInterface
{
    private SplObjectStorage $clients;
    private WebsocketConfiguration $configuration;
    private Logger $logger;

    private array $resourceEvents = [];
    private array $subscriptions = [];

    public function __construct(WebsocketConfiguration $configuration)
    {
        $this->configuration = $configuration;
        $this->logger = $this->configuration->logger;
        $this->clients = new SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->subscribe($conn);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->unsubscribe($conn);
        $this->logger->info("Connection closed ({id})", ["id" => $conn->resourceId]);
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

    public function subscribe(ConnectionInterface $conn)
    {
        $resourceId = $conn->resourceId;

        $path = $conn->httpRequest->getUri()->getPath();
        $event = trim($path, '/ ');

        $this->logger->info("New connection : {id} ({path})", ['id' => $conn->resourceId, "path" => $path]);
        $this->resourceEvents[$resourceId] = $event;
        $this->subscriptions[$event] ??= [];
        $this->subscriptions[$event][$resourceId] = $conn;

    }

    public function unsubscribe(ConnectionInterface $conn)
    {
        $resourceId = $conn->resourceId;
        if (!isset($this->resourceEvents[$resourceId]))
            return;

        $subscribedEvent = $this->resourceEvents[$resourceId];
        unset($this->resourceEvents[$resourceId]);
        unset($this->subscriptions[$subscribedEvent][$resourceId]);
    }

    public function dispatch(string $event, $args)
    {
        $this->configuration->logger->info('Dispatch {event}',['event' => $event]);
        $subscribers = $this->subscriptions[$event] ?? [];

        if (!count($subscribers))
            return;

        $this->configuration->logger->info(
            'Broadcasting {event} to {count} subscribers',
            ['event' => $event, 'count' => count($subscribers)]
        );

        foreach ($subscribers as $resourceId => $connection) {
            $connection->send($args);
        }
    }
}