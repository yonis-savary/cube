<?php 

namespace Cube\Web\Websocket\Servers;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Web\Http\Configuration\CORSConfiguration;

class WebsocketConfiguration extends ConfigurationElement
{
    public const BROADCAST_SECRET_HEADER = 'X-Broadcast-Secret';

    /**
     * @param ?string[] $allowedOrigins Full origins a browser may connect from (`https://app.example.org`), `['*']` for any, the `CORSConfiguration` ones by default
     */
    public function __construct(
        public readonly string $websocketHost = "0.0.0.0",
        public readonly int $websocketPort = 8088,
        public readonly string $httpHost = "127.0.0.1",
        public readonly int $httpPort = 8089,
        public readonly ?string $broadcastSecret = null,
        public readonly ?array $allowedOrigins = null,
    ){}

    /**
     * @return string[]
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins ?? CORSConfiguration::resolve()->allowedOrigins;
    }

    public function allowsOrigin(string $origin): bool
    {
        $allowedOrigins = $this->getAllowedOrigins();

        return '' === $origin
            || in_array('*', $allowedOrigins, true)
            || in_array($origin, $allowedOrigins, true);
    }

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