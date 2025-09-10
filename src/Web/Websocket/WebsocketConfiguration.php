<?php 

namespace Cube\Web\Websocket;

use Cube\Env\Configuration\ConfigurationElement;
use Cube\Env\Logger\Logger;
use Cube\Env\Logger\StdOutLogger;

class WebsocketConfiguration extends ConfigurationElement
{
    public readonly string $websocketAddress;
    public readonly string $websocketInternalAddress;
    public readonly string $apiKey;
    public readonly string $apiKeyHeader;
    public readonly bool $secure;
    public readonly Logger $logger;

    /**
     * @param string $websocketAddress Address used by the Websocket server
     * @param string $websocketInternalAddress Address used by the internal Http server
     * @param string $apiKey API Key used to secure communication between Cube and the Http Server
     * @param string $apiKeyHeader='X-Api-Key' Header used to transmit the API Key
     * @param bool $secure=true Set to `true` to use `https` protocol, `false` for `http`
     * @param ?Logger $logger=null Logger used to log both servers output, use a StdOutLogger if `null` is provided
     */
    public function __construct(
        string $websocketAddress,
        string $websocketInternalAddress,
        string $apiKey,
        string $apiKeyHeader='X-Api-Key',
        bool $secure=true,
        ?Logger $logger=null
    ){
        $this->websocketAddress = $websocketAddress;
        $this->websocketInternalAddress = $websocketInternalAddress;
        $this->apiKey = $apiKey;
        $this->apiKeyHeader = $apiKeyHeader;
        $this->secure = $secure;
        $this->logger = $logger ?? new StdOutLogger;
    }

}