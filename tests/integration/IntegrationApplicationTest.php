<?php

namespace Cube\Tests\Integration;

use Cube\Data\Bunch;
use Cube\Utils\Shell;
use PHPUnit\Framework\TestCase;

class IntegrationApplicationTest extends TestCase
{
    public function testApplicationTestsSuccessfully() {
        Utils::getIntegrationAppStorage();

        $storage = Utils::getDummyApplicationStorage();

        $this->assertTrue($storage->isDirectory('vendor/yonis-savary/cube'));
        $this->assertTrue($storage->isFile('do'));

        $this->assertFileExists($storage->path('App/Models/User.php'));
        $this->assertFileExists($storage->path('App/Models/Module.php'));
        $this->assertFileExists($storage->path('App/Models/ModuleUser.php'));

        $proc = Shell::executeInDirectory('php do test', $storage->getRoot());
        $output = $proc->getOutput() . $proc->getErrorOutput();

        $this->assertEquals(0, $proc->getExitCode(), $output);

        $lastLine = Bunch::fromExplode("\n", $output)->filter()->last();
        $this->assertMatchesRegularExpression("~^OK~", $lastLine, $output);
    }

    public function testQueueLaunchSuccessfully() {

        Utils::getIntegrationAppStorage();

        $storage = Utils::getDummyApplicationStorage();

        $proc = Shell::executeInDirectory('php do add-numbers-to-display', $storage->getRoot());
        $output = $proc->getOutput() . $proc->getErrorOutput();
        $this->assertEquals(0, $proc->getExitCode(), $output);

        $proc = Shell::launchInDirectory('exec php do cube:queue --queue=DisplayerQueue', $storage->getRoot());
        sleep(3);
        $proc->stop();

        $queuesDirectory = $storage->path('Storage/Queues');
        Shell::executeInDirectory('rm -rf Storage/Queues', $storage->getRoot());
        sleep(1);

        $this->assertDirectoryDoesNotExist($queuesDirectory, 'The queue worker outlived the test');

        $logsFile = $storage->path("Storage/Logs/displayerqueue.csv");
        $this->assertFileExists($logsFile);

        $logs = file_get_contents($logsFile);
        $this->assertStringContainsString("DISPLAY : 0", $logs);
        $this->assertStringContainsString("DISPLAY : 29", $logs);
    }

    public function testTheApplicationAnswersOnAUnixSocket() {
        $storage = Utils::getDummyApplicationStorage();
        $socketPath = sys_get_temp_dir().'/'.uniqid('cube-').'.sock';

        $proc = Shell::launchInDirectory("exec php do web:serve --socket={$socketPath}", $storage->getRoot());

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
        $handle = curl_init("http://localhost{$path}");
        curl_setopt_array($handle, [
            CURLOPT_UNIX_SOCKET_PATH => $socketPath,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $body = curl_exec($handle);

        return [curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body];
    }
}
