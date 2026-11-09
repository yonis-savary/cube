<?php

namespace Cube\Tests\Units\Web;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Web\Websocket\Broadcast\Broadcast;
use Cube\Web\Websocket\Broadcast\BroadcastConfiguration;
use Cube\Web\Websocket\Broadcast\LocalBroadcast;
use Cube\Web\Websocket\Channel\ChannelSubscriber;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;
use Cube\Web\Websocket\Servers\WebsocketRouter;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;

/**
 * @internal
 */
class WebsocketMessageTest extends TestCase
{
    private int $nextResourceId = 1;

    public function testAMessageReachesTheChannelOfItsConnection()
    {
        [$sender, $otherPoll, $samePoll] = $this->inRouter(function (WebsocketRouter $router) {
            $sender = $this->connect($router, '/vote/7');
            $otherPoll = $this->connect($router, '/vote/8');
            $samePoll = $this->connect($router, '/vote/7');

            $router->onMessage($sender, '{"score": 5}');

            return [$sender, $otherPoll, $samePoll];
        });

        $expected = [json_encode(['poll' => '7', 'score' => 5])];
        $this->assertEquals($expected, $sender->sent);
        $this->assertEquals($expected, $samePoll->sent);
        $this->assertEquals([], $otherPoll->sent);
    }

    public function testAChannelWithoutMessageRuleIgnoresMessages()
    {
        $connection = $this->inRouter(function (WebsocketRouter $router) {
            $connection = $this->connect($router, '/product/1');
            $router->onMessage($connection, '{"score": 5}');

            return $connection;
        });

        $this->assertEquals([], $connection->sent);
        $this->assertFalse($connection->closed);
    }

    public function testAnInvalidMessageIsAnsweredWithoutClosing()
    {
        foreach (['not json', '12'] as $message) {
            $connection = $this->sendTo('/vote/7', $message);
            $this->assertEquals([json_encode(['error' => 'Message must be a JSON object'])], $connection->sent);
            $this->assertFalse($connection->closed);
        }

        $connection = $this->sendTo('/vote/7', '{"score": "high"}');
        $this->assertArrayHasKey('score', json_decode($connection->sent[0], true)['error']);
        $this->assertFalse($connection->closed);
    }

    /** Ratchet only catches \Exception : a \DivisionByZeroError left the event loop and killed the process. */
    public function testAnErrorInAHandlerStaysInTheConnection()
    {
        $connection = $this->sendTo('/vote/7', '{"score": 0}');

        $this->assertEquals([json_encode(['error' => 'Internal error'])], $connection->sent);
        $this->assertFalse($connection->closed);
    }

    public function testAConnectionCannotExceedTheMessageLimit()
    {
        $connection = $this->inRouter(function (WebsocketRouter $router) {
            $connection = $this->connect($router, '/vote/7');
            $router->onMessage($connection, '{"score": 1}');
            $router->onMessage($connection, '{"score": 2}');

            return $connection;
        }, new WebsocketConfiguration(maxMessagesPerMinute: 1));

        $this->assertEquals(json_encode(['error' => 'Too many messages']), $connection->sent[1]);
    }

    public function testTheMessageAllowanceRenewsEveryMinute()
    {
        $subscriber = new ChannelSubscriber('/vote/7', $this->connection());

        $this->assertTrue($subscriber->consumeMessageAllowance(1, 1000));
        $this->assertFalse($subscriber->consumeMessageAllowance(1, 1059));
        $this->assertTrue($subscriber->consumeMessageAllowance(1, 1060));
    }

    public function testLocalBroadcastRefusesAnUnknownChannel()
    {
        $broadcast = new LocalBroadcast(new BroadcastConfiguration(), new WebsocketRouter(new WebsocketConfiguration()));

        $this->assertFalse($broadcast->emit('/vote/7', ['__class' => 'NoSuchChannel']));
    }

    private function sendTo(string $path, string $message): ConnectionInterface
    {
        return $this->inRouter(function (WebsocketRouter $router) use ($path, $message) {
            $connection = $this->connect($router, $path);
            $router->onMessage($connection, $message);

            return $connection;
        });
    }

    private function inRouter(callable $callback, ?WebsocketConfiguration $configuration = null): mixed
    {
        return Logger::withInstance(new NullLogger(), function () use ($callback, $configuration) {
            $router = new WebsocketRouter($configuration ?? new WebsocketConfiguration());

            return Broadcast::withInstance(
                new LocalBroadcast(new BroadcastConfiguration(), $router),
                fn () => $callback($router)
            );
        });
    }

    private function connect(WebsocketRouter $router, string $path): ConnectionInterface
    {
        $connection = $this->connection();
        $connection->httpRequest = new Request('GET', "ws://localhost:8088{$path}");
        $router->onOpen($connection);

        return $connection;
    }

    private function connection(): ConnectionInterface
    {
        $connection = new class implements ConnectionInterface {
            public int $resourceId = 0;
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
        $connection->resourceId = $this->nextResourceId++;

        return $connection;
    }
}
