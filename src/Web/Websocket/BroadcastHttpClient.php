<?php 

namespace Cube\Web\Websocket;

use Cube\Http\HttpClient;
use Cube\Logger\Logger;

/**
 * This class is a simple HTTPClient using the websocket configuration
 * used to send requests to the HTTP Server of the websocket service
 */
class BroadcastHttpClient extends HttpClient
{
    protected WebsocketConfiguration $configuration;
    protected string $host;
    protected array $baseHeaders;

    public function __construct()
    {
        $this->configuration = $configuration = WebsocketConfiguration::resolve();
        $this->host =
            ($configuration->secure ? "https": "http") .
            "://" .
            $configuration->websocketInternalAddress;

        $this->baseHeaders = [];
        $this->baseHeaders[$configuration->apiKeyHeader] = $configuration->apiKey;
    }

    public function baseLogger(): Logger
    {
        return new Logger('broadcast.php');
    }

    public function baseURL(): string {
        return $this->host;
    }

    public function baseHeaders(): array {
        return $this->baseHeaders;
    }
}