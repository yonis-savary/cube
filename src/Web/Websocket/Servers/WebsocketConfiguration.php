<?php 

namespace Cube\Web\Websocket\Servers;

use Cube\Env\Configuration\ConfigurationElement;

class WebsocketConfiguration extends ConfigurationElement
{
    public const BROADCAST_SECRET_HEADER = 'X-Broadcast-Secret';

    public function __construct(
        public readonly string $websocketHost = "0.0.0.0",
        public readonly int $websocketPort = 8088,
        public readonly string $httpHost = "0.0.0.0",
        public readonly int $httpPort = 8089,
        public readonly ?string $broadcastSecret = null,
    ){}

    public function getWebsocketOrigin(): string
    {
        $host = $this->websocketHost;
        $port = $this->websocketPort;

        return "$host:$port";
    }
    public function getHttpOrigin(): string
    {
        $host = $this->httpHost;
        $port = $this->httpPort;

        return "$host:$port";
    }
}