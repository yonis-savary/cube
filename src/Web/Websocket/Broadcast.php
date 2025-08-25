<?php 

namespace Cube\Web\Websocket;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Cube\Web\Http\HttpClient;

class Broadcast
{
    use Component;

    protected HttpClient $httpClient;

    public function __construct(BroadcastHttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function emit(string $event, $data): bool {

        $response = $this->httpClient->postJson("/$event", $data);
        if ($response->isOk()) {
            return true;
        }

        $logger = Logger::getInstance();
        $logger->error("Could not emit broadcast event [$event] !");
        $logger->error($response->getBody());

        return false;
    }
}