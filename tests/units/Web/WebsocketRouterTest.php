<?php

namespace Cube\Tests\Units\Web;

use App\Channels\ProductChannel;
use Cube\Web\Http\StatusCode;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;
use Cube\Web\Websocket\Servers\WebsocketRouter;
use PHPUnit\Framework\TestCase;
use React\Http\Message\ServerRequest;

/**
 * @internal
 */
class WebsocketRouterTest extends TestCase
{
    /** The broadcast HTTP endpoint relayed any POST to subscribers, with no credential asked. */
    public function testABroadcastWithoutTheSecretIsRefused()
    {
        $this->assertEquals(StatusCode::FORBIDDEN, $this->broadcast([])->getStatusCode());
        $this->assertEquals(StatusCode::FORBIDDEN, $this->broadcast([WebsocketConfiguration::BROADCAST_SECRET_HEADER => 'guessed'])->getStatusCode());
    }

    public function testABroadcastWithTheSecretIsRelayed()
    {
        $response = $this->broadcast([WebsocketConfiguration::BROADCAST_SECRET_HEADER => 's3cret']);

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
    }

    public function testWithoutASecretEveryBroadcastIsRelayed()
    {
        $response = $this->broadcast([], new WebsocketConfiguration());

        $this->assertEquals(StatusCode::OK, $response->getStatusCode());
    }

    protected function broadcast(array $headers, ?WebsocketConfiguration $configuration = null)
    {
        $request = new ServerRequest(
            'POST',
            'http://localhost:8089/product/1',
            ['Content-Type' => 'application/json', ...$headers],
            json_encode(['__class' => ProductChannel::class, 'message' => 'hello'])
        );

        $router = new WebsocketRouter($configuration ?? new WebsocketConfiguration(broadcastSecret: 's3cret'));

        return ($router->getHttpServerCallback())($request);
    }
}
