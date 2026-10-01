<?php 

namespace Cube\Web\Websocket\Broadcast;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Cube\Web\Http\HttpClient;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;

/**
 * This class is a simple HTTPClient using the websocket configuration
 * used to send requests to the HTTP Server of the websocket service
 */
class Broadcast extends HttpClient
{
    use Component;

    public function __construct(
        protected readonly BroadcastConfiguration $configuration
    )
    {}

    public function baseLogger(): Logger
    {
        return Logger::forFile('broadcast-client.csv');
    }

    public function baseHeaders(): array
    {
        $secret = $this->configuration->getBroadcastSecret();

        return null === $secret ? [] : [WebsocketConfiguration::BROADCAST_SECRET_HEADER => $secret];
    }

    public function baseURL(): string {
        return $this->configuration->getHttpOrigin();
    }

    public function emit(string $event, $data): bool {
        $event = trim($event, "/");
        return $this->postJsonAsync($event, $data);
    }
}