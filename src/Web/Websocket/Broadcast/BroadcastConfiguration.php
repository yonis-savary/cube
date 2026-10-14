<?php 

namespace Cube\Web\Websocket\Broadcast;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;

class BroadcastConfiguration extends ConfigurationElement
{
    protected const WILDCARD_HOSTS = ['0.0.0.0', '::', '[::]'];

    /**
     * By default http part of WebsocketConfiguration shall be used
     */
    public function __construct(
        protected ?string $socketHost = null,
        protected ?int $socketPort = null,
        protected ?string $httpHost = null,
        protected ?int $httpPort = null,
        protected ?string $broadcastSecret = null,
    ){}

    public function getBroadcastSecret(): ?string
    {
        return $this->broadcastSecret ?? WebsocketConfiguration::resolve()->broadcastSecret;
    }

    public function getHttpOrigin(): string
    {
        $websocketConfig = WebsocketConfiguration::resolve();
        $host = $this->httpHost ?? $websocketConfig->httpHost;
        $port = $this->httpPort ?? $websocketConfig->httpPort;
        if (!str_contains($host, "://"))
            $host = "http://$host";

        return "$host:$port";
    }

    public function getWebsocketOrigin(): string
    {
        $websocketConfig = WebsocketConfiguration::resolve();
        $host = $this->socketHost ?? $this->toReachableHost($websocketConfig->websocketHost);
        $port = $this->socketPort ?? $websocketConfig->websocketPort;
        if (!str_contains($host, "://"))
            $host = "ws://$host";

        return "$host:$port";
    }

    protected function toReachableHost(string $boundHost): string
    {
        return in_array($boundHost, self::WILDCARD_HOSTS, true)
            ? 'localhost'
            : $boundHost;
    }
}