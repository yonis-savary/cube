<?php

namespace Cube\Tests\Integration\Application;

use Cube\Tests\Integration\Utils;
use Cube\Utils\Shell;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function testQueueLaunchSuccessfully() {

        Utils::getIntegrationAppStorage();

        $storage = Utils::getDummyApplicationStorage();

        $proc = Shell::executeInDirectory('php do add-numbers-to-display', $storage->getRoot());
        $output = $proc->getOutput() . $proc->getErrorOutput();
        $this->assertEquals(0, $proc->getExitCode(), $output);

        $logsFile = $storage->path("Storage/Logs/displayerqueue.csv");

        $proc = Shell::launchInDirectory('exec php do cube:queue --queue=DisplayerQueue', $storage->getRoot());

        for ($i = 0; $i < 50 && !$this->fileContains($logsFile, 'DISPLAY : 29'); $i++)
            usleep(100_000);

        $proc->stop();

        $queuesDirectory = $storage->path('Storage/Queues');
        Shell::executeInDirectory('rm -rf Storage/Queues', $storage->getRoot());

        // A surviving worker polls every 50 ms and would recreate the directory within this delay
        usleep(200_000);

        $this->assertDirectoryDoesNotExist($queuesDirectory, 'The queue worker outlived the test');

        $this->assertFileExists($logsFile);

        $logs = file_get_contents($logsFile);
        $this->assertStringContainsString("DISPLAY : 0", $logs);
        $this->assertStringContainsString("DISPLAY : 29", $logs);
    }

    protected function fileContains(string $file, string $needle): bool
    {
        return is_file($file) && str_contains(file_get_contents($file), $needle);
    }
}
