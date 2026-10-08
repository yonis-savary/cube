<?php

namespace Cube\Tests\Integration\Application;

use Cube\Tests\Integration\Utils;
use Cube\Utils\Shell;
use Cube\Web\Http\HttpClient;
use PHPUnit\Framework\TestCase;

class UnixSocketTest extends TestCase
{
    public function testTheApplicationAnswersOnAUnixSocket() {
        $storage = Utils::getDummyApplicationStorage();
        $socketPath = sys_get_temp_dir().'/'.uniqid('cube-').'.sock';

        $proc = Shell::launchInDirectory("exec php do unix:serve {$socketPath}", $storage->getRoot());

        try {
            for ($i = 0; $i < 50 && !file_exists($socketPath); $i++)
                usleep(100_000);

            $this->assertFileExists($socketPath, $proc->getOutput().$proc->getErrorOutput());

            [$pingStatus, $pingBody] = $this->fetchThroughSocket($socketPath, '/internal/ping');
            [$discoveredStatus] = $this->fetchThroughSocket($socketPath, '/ping');

            $this->assertEquals(200, $pingStatus);
            $this->assertEquals('"OK"', $pingBody);
            $this->assertEquals(404, $discoveredStatus, 'Controllers must not be discovered by the socket router');
        } finally {
            $proc->stop();
        }

        $this->assertFileDoesNotExist($socketPath, 'The socket file outlived the server');
    }

    /** @return array{int,string} */
    protected function fetchThroughSocket(string $socketPath, string $path): array {
        $client = new class($socketPath) extends HttpClient {
            public function __construct(protected string $socketPath) {}

            public function baseUnixSocket(): ?string
            {
                return $this->socketPath;
            }
        };

        $response = $client->get($path);

        return [$response->getStatusCode(), $response->getBody()];
    }
}
