<?php

namespace Cube\Web\Websocket\Channel;

use Cube\Env\Logger\Logger;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\Rules\Rule;
use Cube\Web\Http\StatusCode;
use Cube\Web\Router\Route;
use Cube\Web\Websocket\Broadcast\Broadcast;
use Cube\Web\Websocket\Broadcast\BroadcastConfiguration;
use Psr\Http\Message\RequestInterface;
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

    protected function getDummyRoute(): Route
    {
        return new Route($this->getRoute(), fn () => null);
    }

    protected function log(string $message): void
    {
        $this->logger ??= Logger::getInstance();
        $this->logger->info(preg_replace('~.+\\\\~', '', static::class).' '.$message);
    }

    /**
     * This method act as a middleware called
     * before a connection subscribes to the channel.
     *
     * @param array<string,string> $slugs   Slug values of the subscribed path
     * @param RequestInterface     $request Handshake request : read the client's cookies, headers or query string from it
     *
     * @return ?string null => the subscription is authorized, string => error returned to the client through the socket
     */
    abstract public function authorize(array $slugs, RequestInterface $request): ?string;

    /**
     * @return ?array Slug values if matching, null otherwise
     */
    public function match(string $requestPath): ?array
    {
        $request = new Request('GET', $requestPath);
        $match = $this->getDummyRoute()->match($request);

        return $match
            ? $request->getSlugValues()
            : null;
    }

    public function path(array $routeParams = []): string
    {
        return $this->cachedPath ?? $this->getDummyRoute()->buildPath($routeParams);
    }

    public function emit(array $data, array $routeParams = []): bool
    {
        $data['__class'] = static::class;
        return Broadcast::getInstance()->emit(
            $this->path($routeParams),
            $data
        );
    }

    public function lockParams(array $params = []): void
    {
        $this->routeParams = $params;
        $this->cachedPath = null;
        $this->cachedPath = $this->path($params);
    }

    public function unlockParams(): void
    {
        $this->routeParams = [];
        $this->cachedPath = null;
    }

    /**
     * @return ?Rule null => messages sent by clients are ignored
     */
    public function getMessageRule(): ?Rule
    {
        return null;
    }

    /**
     * @param array<mixed> $data Result of `getMessageRule()`
     */
    public function onMessage(ChannelSubscriber $from, array $data): void {}

    final public function receive(ChannelSubscriber $from, string $message, ?int $maxMessagesPerMinute = null): void
    {
        if (!$rule = $this->getMessageRule())
            return;
        if (null !== $maxMessagesPerMinute && !$from->consumeMessageAllowance($maxMessagesPerMinute)) {
            $from->send(['error' => 'Too many messages']);
            return;
        }

        $data = json_decode($message, true);
        if (!is_array($data)) {
            $from->send(['error' => 'Message must be a JSON object']);
            return;
        }

        $validation = $rule->validate($data);
        if (!$validation->isValid()) {
            $from->send(['error' => $validation->getErrors()]);
            return;
        }

        $this->onMessage($from, $validation->getResult());
    }

    public function getSubscriber(ConnectionInterface $connection): ?ChannelSubscriber
    {
        return $this->subscribers[$connection->resourceId] ?? null;
    }

    /**
     * @param array<string,string> $slugs Slug values of the subscribed path
     */
    public function subscribe(string $path, ConnectionInterface $connection, array $slugs = [])
    {
        $resourceId = $connection->resourceId;
        $this->log("new subscriber {$resourceId} ({$path})");
        $this->subscribers[$resourceId] = new ChannelSubscriber($path, $connection, $slugs);
    }

    public function dispatch(string $path, array $data = [])
    {
        $dispatchedCount = 0;
        foreach ($this->subscribers as $_ => $subscriber) {
            if ($subscriber->path === $path) {
                $subscriber->send($data);
                ++$dispatchedCount;
            }
        }
        $this->log("dispatched event to {$dispatchedCount} subscribers ({$path})");
    }

    public function unsubscribe(ConnectionInterface $connection): bool
    {
        if (!array_key_exists($connection->resourceId, $this->subscribers)) {
            return false;
        }
        unset($this->subscribers[$connection->resourceId]);
        return true;
    }

    public function redirect(array $routeParams = []): Response
    {
        $broadCastConfiguration = BroadcastConfiguration::resolve();

        $path = rtrim($broadCastConfiguration->getWebsocketOrigin(), '/').'/'.ltrim($this->path($routeParams), '/');

        return new Response(StatusCode::TEMPORARY_REDIRECT, headers: [
            'Location' => $path,
        ]);
    }
}
