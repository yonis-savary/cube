<?php 

namespace Cube\Web\Websocket\Broadcast;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;

class BroadcastConfiguration extends ConfigurationElement
{
    /**
     * By default http part of WebsocketConfiguration shall be used
     */
    public function __construct(
        protected ?string $socketHost = null,
        protected ?int $socketPort = null,
        protected ?string $httpHost = null,
        protected ?int $httpPort = null
    ){}

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
        $host = $this->socketHost ?? $websocketConfig->websocketHost;
        $port = $this->socketPort ?? $websocketConfig->websocketPort;
        if (!str_contains($host, "://"))
            $host = "ws://$host";

        return "$host:$port";
    }
}