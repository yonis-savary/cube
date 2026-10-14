<?php 

namespace Cube\Web\Websocket\Broadcast;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Cube\Web\Http\HttpClient;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;
use RuntimeException;

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

    /**
     * @return bool `true` once the websocket server accepted and dispatched the signal
     */
    public function emit(string $event, array $data): bool
    {
        try {
            return $this->postJson('/' . trim($event, '/'), $data)->isOk();
        } catch (RuntimeException $exception) {
            $this->baseLogger()->error('Could not reach the websocket server : {message}', ['message' => $exception->getMessage()]);
            return false;
        }
    }
}