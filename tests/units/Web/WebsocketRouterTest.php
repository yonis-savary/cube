<?php

namespace Cube\Tests\Units\Web;

use App\Channels\ProductChannel;
use Cube\Env\Configuration;
use Cube\Web\Http\Configuration\CORSConfiguration;
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

    /** `text/plain; x=application/json` passed the substring check while staying a request a browser sends cross-site without preflight. */
    public function testABroadcastMustBeExactlyJson()
    {
        $response = $this->broadcast(['Content-Type' => 'text/plain; x=application/json'], new WebsocketConfiguration());

        $this->assertEquals(StatusCode::BAD_REQUEST, $response->getStatusCode());
    }

    public function testTheBroadcastServerRefusesToBeExposedWithoutASecret()
    {
        $this->assertTrue((new WebsocketConfiguration(httpHost: '0.0.0.0'))->exposesBroadcastWithoutSecret());
        $this->assertFalse((new WebsocketConfiguration(httpHost: '0.0.0.0', broadcastSecret: 's3cret'))->exposesBroadcastWithoutSecret());
        $this->assertFalse((new WebsocketConfiguration())->exposesBroadcastWithoutSecret());
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

    /** A route without slugs matched with an empty array, which onOpen() took for no match at all. */
    public function testAChannelWithoutSlugsAcceptsSubscribers()
    {
        $this->assertTrue($this->subscribe('/announcements', [])->closed);
        $this->assertFalse($this->subscribe('/announcements', ['Authorization' => 'Bearer reader'])->closed);
    }

    /** A page on any site could open a connection carrying its visitor's cookies, and read their private events. */
    public function testABrowserMustComeFromAnAllowedOrigin()
    {
        $configuration = new WebsocketConfiguration(broadcastSecret: 's3cret', allowedOrigins: ['https://shop.example.org']);
        $authorized = ['Authorization' => 'Bearer token-7'];

        $this->assertTrue($this->subscribe('/private/7', [...$authorized, 'Origin' => 'https://evil.example.org'], $configuration)->closed);
        $this->assertFalse($this->subscribe('/private/7', [...$authorized, 'Origin' => 'https://shop.example.org'], $configuration)->closed);
        $this->assertFalse($this->subscribe('/private/7', $authorized, $configuration)->closed);
    }

    public function testTheAllowedOriginsDefaultToTheCorsOnes()
    {
        $allowedOrigins = Configuration::withInstance(
            new Configuration(new CORSConfiguration(['https://shop.example.org'])),
            fn () => (new WebsocketConfiguration())->getAllowedOrigins()
        );

        $this->assertEquals(['https://shop.example.org'], $allowedOrigins);
    }

    /** A connection to a path no channel answers was left open, so anyone could pile them up until the process ran out of resources. */
    public function testAConnectionNoChannelAnswersIsClosed()
    {
        $this->assertTrue($this->subscribe('/no-such-channel', [])->closed);
    }

    protected function subscribe(string $path, array $headers, ?WebsocketConfiguration $configuration = null): ConnectionInterface
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

        $this->router($configuration)->onOpen($connection);

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
