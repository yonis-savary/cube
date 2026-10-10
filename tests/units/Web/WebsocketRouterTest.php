<?php

namespace Cube\Tests\Units\Web;

use App\Channels\ProductChannel;
use Cube\Web\Http\StatusCode;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;
use Cube\Web\Websocket\Servers\WebsocketRouter;
use Cube\Tests\Units\Web\Classes\PrivateChannel;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;
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

    /** The broadcast server answered preflights with a wildcard origin, letting any web page emit. */
    public function testTheBroadcastServerSendsNoCorsHeaders()
    {
        $response = $this->broadcast([WebsocketConfiguration::BROADCAST_SECRET_HEADER => 's3cret']);
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));

        $preflight = ($this->router()->getHttpServerCallback())(new ServerRequest('OPTIONS', 'http://localhost:8089/product/1'));
        $this->assertFalse($preflight->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testTheBroadcastServerIsPrivateByDefault()
    {
        $this->assertEquals('127.0.0.1:8089', (new WebsocketConfiguration())->getHttpOrigin());
    }

    public function testAChannelAuthorizesFromTheHandshakeRequest()
    {
        $accepted = $this->subscribe('/private/7', ['Authorization' => 'Bearer token-7']);
        $this->assertFalse($accepted->closed);

        $refused = $this->subscribe('/private/7', ['Authorization' => 'Bearer token-8']);
        $this->assertTrue($refused->closed);
        $this->assertEquals([json_encode(['error' => 'Invalid token'])], $refused->sent);
    }

    protected function subscribe(string $path, array $headers): ConnectionInterface
    {
        $connection = new class implements ConnectionInterface {
            public int $resourceId = 1;
            public mixed $httpRequest = null;
            public array $sent = [];
            public bool $closed = false;

            public function send($data)
            {
                $this->sent[] = $data;
                return $this;
            }

            public function close()
            {
                $this->closed = true;
            }
        };
        $connection->httpRequest = new Request('GET', "ws://localhost:8088{$path}", $headers);

        $this->router()->onOpen($connection);

        return $connection;
    }

    protected function router(?WebsocketConfiguration $configuration = null): WebsocketRouter
    {
        return new WebsocketRouter($configuration ?? new WebsocketConfiguration(broadcastSecret: 's3cret'));
    }

    protected function broadcast(array $headers, ?WebsocketConfiguration $configuration = null)
    {
        $request = new ServerRequest(
            'POST',
            'http://localhost:8089/product/1',
            ['Content-Type' => 'application/json', ...$headers],
            json_encode(['__class' => ProductChannel::class, 'message' => 'hello'])
        );

        return ($this->router($configuration)->getHttpServerCallback())($request);
    }
}
