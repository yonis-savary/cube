<?php

namespace Cube\Web\Websocket\Channel;

use Cube\Env\Logger\Logger;
use Cube\Utils\Path;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use Cube\Web\Websocket\Broadcast\Broadcast;
use Cube\Web\Websocket\Broadcast\BroadcastConfiguration;
use Cube\Web\Websocket\Channel\ChannelSubscriber;
use Ratchet\ConnectionInterface;

abstract class Channel implements ChannelInterface
{
    /**
     * @var array<string,ChannelSubscriber>
     */
    protected array $subscribers = [];

    protected array $routeParams = [];

    protected ?string $cachedPath = null;

    protected ?Logger $logger = null;

    protected function getDummyRoute():Route {
        return new Route($this->getRoute(), fn() => null);
    }

    protected function log(string $message): void
    {
        $this->logger ??= Logger::getInstance();
        $this->logger->info(preg_replace("~.+\\\\~", "", static::class) . " " . $message);
    }

    /**
     * This method act as a middleware called
     * before a connection subscribes to the channel
     *
     * @return ?string null => the subscription is authorized, string => error returned to the client through the socket
     */
    public function authorize(array $slugs=[]): ?string {
        return null;
    }

    /**
     * @return ?array Slug values if matching, null otherwise
     */
    public function match(string $requestPath): ?array {
        $request = new Request("GET", $requestPath);
        $match = $this->getDummyRoute()->match($request);

        return $match
            ? $request->getSlugValues()
            : null;
    }

    public function path(array $routeParams=[]): string {
        return $this->cachedPath ?? $this->getDummyRoute()->buildPath($routeParams);
    }

    public function emit(array $data, array $routeParams=[]): bool {
        $data["__class"] = static::class;
        return Broadcast::getInstance()->emit(
            $this->path($routeParams),
            $data
        );
    }

    public function lockParams(array $params=[]): void {
        $this->routeParams = $params;
        $this->cachedPath = null;
        $this->cachedPath = $this->path($params);
    }

    public function unlockParams(): void {
        $this->routeParams = [];
        $this->cachedPath = null;
    }

    public function subscribe(string $path, ConnectionInterface $connection) {
        $resourceId = $connection->resourceId;
        $this->log("new subscriber $resourceId ($path)");
        $this->subscribers[$resourceId] = new ChannelSubscriber($path, $connection);
    }

    public function dispatch(string $path, array $data=[]) {
        $dispatchedCount = 0;
        foreach ($this->subscribers as $_ => $subscriber) {
            if ($subscriber->path === $path) {
                $subscriber->connection->send(json_encode($data, JSON_THROW_ON_ERROR));
                $dispatchedCount++;
            }
        }
        $this->log("dispatched event to $dispatchedCount subscribers ($path)");
    }

    public function unsubscribe(ConnectionInterface $connection): bool {
        if (!array_key_exists($connection->resourceId, $this->subscribers)) {
            return false;
        }
        unset($this->subscribers[$connection->resourceId]);
        return true;
    }


    public function redirect(array $routeParams=[]): Response
    {
        $broadCastConfiguration = BroadcastConfiguration::resolve();

        $path = Path::join(
            $broadCastConfiguration->getWebsocketOrigin(),
            $this->path($routeParams)
        );

        return new Response(StatusCode::TEMPORARY_REDIRECT, headers: [
            "Location" => $path
        ]);
    }

}