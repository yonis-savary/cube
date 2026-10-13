<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Websocket\Serve;
use Cube\Env\Logger\NullLogger;
use Cube\Env\Logger\StdOutLogger;
use Cube\Tests\Units\Console\Classes\RecordingWebsocket;
use Cube\Web\Websocket\Servers\Websocket;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class WebsocketServeTest extends TestCase
{
    public function testTheLogOptionEchoesTheServerLogToStdOut()
    {
        $this->assertInstanceOf(StdOutLogger::class, $this->loggerWhileServing(['--log']));
    }

    public function testTheServerIsSilentWithoutTheLogOption()
    {
        $this->assertInstanceOf(NullLogger::class, $this->loggerWhileServing([]));
    }

    private function loggerWhileServing(array $argv): mixed
    {
        $websocket = inject(RecordingWebsocket::class);
        Websocket::withInstance($websocket, fn () => (new Serve())->execute(Args::fromArgv($argv)));

        return $websocket->loggerWhileServing;
    }
}
