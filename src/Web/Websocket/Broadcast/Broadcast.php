<?php 

namespace Cube\Web\Websocket\Broadcast;

use Cube\Core\Component;
use Cube\Env\Logger\Logger;
use Cube\Web\Http\HttpClient;

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
        return new Logger('broadcast-client');
    }

    public function baseURL(): string {
        return $this->configuration->getHttpOrigin();
    }

    public function emit(string $event, $data){
        $event = trim($event, "/");
        $data["__class"] = get_called_class();
        $this->postJsonAsync($event, $data);
    }
}