<?php

namespace Tests\Integration;

use Cube\Core\Autoloader;
use Cube\Web\Http\Request;
use Cube\Env\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class WebsocketTest extends TestCase
{
    protected Process $process;
    protected Logger $logger;

    public function setUp(): void
    {
        $this->logger = new Logger('websocket-server.csv');

        $this->process = new Process(['php','do','websocket:serve'], Autoloader::getProjectPath());
        $this->process->start(fn() => $this->log());
        $this->assertTrue($this->process->isRunning());

        sleep(1);
    }

    public function tearDown(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop();
        }
    }

    public function log()
    {
        $logger = $this->logger;

        $logger->info($this->process->getIncrementalOutput());
        $logger->info($this->process->getIncrementalErrorOutput());
    }

    public function testHttpServer() {
        $this->assertTrue($this->process->isRunning());

        $request = new Request(
            'POST',
            'http://127.0.0.1:9992/some-event',
            post: ["some" => "value"],
            headers: [
                "X-Api-Key" => "supersecret",
                "Content-Type" => "application/json"
            ]
        );

        $response = $request->fetch();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("OK", $response->getBody());


        $unauthenticatedRequest = new Request(
            'POST',
            'http://127.0.0.1:9992/some-event',
            post: ["some" => "value"],
            headers: ["Content-Type" => "application/json"]
        );

        $response = $unauthenticatedRequest->fetch();
        $this->assertEquals(401, $response->getStatusCode());
    }
}