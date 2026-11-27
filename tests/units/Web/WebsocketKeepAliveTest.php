<?php

namespace Cube\Tests\Units\Web;

use Cube\Data\Database\Database;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Env\Logger\NullLogger;
use Cube\Tests\Units\Database\Classes\LosesItsConnection;
use Cube\Tests\Units\Database\DatabaseProvider;
use Cube\Web\Websocket\Servers\Websocket;
use Cube\Web\Websocket\Servers\WebsocketConfiguration;
use Cube\Web\Websocket\Servers\WebsocketRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;

/**
 * @internal
 */
class WebsocketKeepAliveTest extends TestCase
{
    use LosesItsConnection;

    #[DataProvider('getServerProviders')]
    public function testTheServerReconnectsALostDatabase(DatabaseProvider $provider)
    {
        $database = $this->reconnectableDatabase($provider);
        $this->closeFromTheServer($database, $provider);

        $database->asGlobalInstance(fn () => $this->runForASecondAndAHalf(new DatabaseConfiguration(keepAliveInterval: 1)));

        $this->assertTrue($database->ping());
    }

    public function testAServerThatNeverUsedTheDatabaseOpensNoConnection()
    {
        Database::withInstance(null, function () {
            $this->runForASecondAndAHalf(new DatabaseConfiguration(keepAliveInterval: 1));

            $this->assertFalse(Database::hasInstance());
        });
    }

    protected function runForASecondAndAHalf(DatabaseConfiguration $configuration): void
    {
        $loop = new StreamSelectLoop();
        $websocketConfiguration = new WebsocketConfiguration();
        $server = new class(new NullLogger(), $websocketConfiguration, new WebsocketRouter($websocketConfiguration)) extends Websocket {
            public function publicKeepDatabaseAlive(StreamSelectLoop $loop, DatabaseConfiguration $configuration): void
            {
                $this->keepDatabaseAlive($loop, $configuration);
            }
        };

        $server->publicKeepDatabaseAlive($loop, $configuration);
        $loop->addTimer(1.5, fn () => $loop->stop());
        $loop->run();
    }
}
