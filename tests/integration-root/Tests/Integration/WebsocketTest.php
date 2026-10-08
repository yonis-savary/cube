<?php

namespace Tests\Integration;

use App\Channels\ProductChannel;
use Cube\Core\Injector;
use Cube\Web\Http\Request;
use Cube\Env\Logger\Logger;
use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class WebsocketTest extends TestCase
{
    protected Process $process;
    protected Logger $logger;

    public function setUp(): void
    {
        $this->logger = Logger::forFile('websocket-server.csv');

        $this->process = new Process(['php','do','websocket:serve'], Path::getProjectPath());
        $this->process->start(fn() => $this->log());
        $this->assertTrue($this->process->isRunning());

        $this->waitForHttpPort();
    }

    protected function waitForHttpPort(): void
    {
        for ($i = 0; $i < 50; $i++) {
            // A refused connection is the expected outcome until the server has bound its port
            if ($connection = @fsockopen('127.0.0.1', 9992)) {
                fclose($connection);
                return;
            }

            usleep(100_000);
        }

        $this->fail('The websocket server never opened port 9992 : '.$this->process->getErrorOutput());
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
            'http://127.0.0.1:9992/product/1',
            post: [
                "__class" => ProductChannel::class,
                "some" => "value"
            ],
            headers: [
                "X-Api-Key" => "supersecret",
                "Content-Type" => "application/json"
            ]
        );

        $response = $request->fetch();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("OK", $response->getBody());
    }

    public function testEmitFromChannel() {
        $this->assertTrue($this->process->isRunning());
        $channel = Injector::getInstance()->instanciate(ProductChannel::class);

        $channel->lockParams([1]);
        $this->assertTrue($channel->emit(["some" => 'value']));
    }
}