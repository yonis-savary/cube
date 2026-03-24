<?php 

namespace Cube\Web\Websocket\Broadcast;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;

class BroadcastConfiguration extends ConfigurationElement
{
    public string $httpHost;
    public int $httpPort;
    public string $socketHost;
    public int $socketPort;

    /**
     * By default http part of WebsocketConfiguration shall be used
     */
    public function __construct(
        ?string $httpHost = null,
        ?int $httpPort = null,
        ?string $socketHost = null,
        ?int $socketPort = null
    ){
        if (!$httpHost || !$httpPort) {
            $websocketConfig = WebsocketConfiguration::resolve();
            $httpHost ??= $websocketConfig->httpHost;
            $httpPort ??= $websocketConfig->httpPort;
            $socketHost ??= $websocketConfig->websocketHost;
            $socketPort ??= $websocketConfig->websocketPort;
        }
        $this->httpHost = $httpHost;
        $this->httpPort = $httpPort;
        $this->socketHost = $socketHost;
        $this->socketPort = $socketPort;
    }

    public function getHttpOrigin(): string
    {
        $host = $this->httpHost;
        $port = $this->httpPort;
        if (!str_contains($host, "://"))
            $host = "http://$host";

        return "$host:$port";
    }

    public function getWebsocketOrigin(): string
    {
        $host = $this->socketHost;
        $port = $this->socketPort;
        if (!str_contains($host, "://"))
            $host = "ws://$host";

        return "$host:$port";
    }
}