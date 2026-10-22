<?php

namespace Cube\Tests\Integration\Application;

use Cube\Tests\Integration\Utils;
use Cube\Utils\Shell;
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase
{
    public function testTasksRunInParallel()
    {
        $storage = Utils::getDummyApplicationStorage();
        $logsFile = $storage->path('Storage/Logs/launch.csv');
        $logsBefore = $this->readLogs($logsFile);

        $start = microtime(true);
        $proc = Shell::executeInDirectory('php do routine:launch', $storage->getRoot());
        $elapsed = microtime(true) - $start;

        $this->assertEquals(0, $proc->getExitCode(), $proc->getOutput().$proc->getErrorOutput());

        $logs = substr($this->readLogs($logsFile), strlen($logsBefore));
        $this->assertStringContainsString('SlowReport finished', $logs);
        $this->assertStringContainsString('Heartbeat ping', $logs);

        $heartbeatPosition = strpos($logs, 'Heartbeat ping');
        $this->assertLessThan(strpos($logs, 'SlowReport finished'), $heartbeatPosition, 'Heartbeat waited for the slow task');
        $this->assertLessThan(4, $elapsed);
    }

    /** A run starting while the previous one was still busy ran the same task twice at once. */
    public function testATaskStillRunningIsSkipped()
    {
        $storage = Utils::getDummyApplicationStorage();
        $logsFile = $storage->path('Storage/Logs/launch.csv');
        $logsBefore = $this->readLogs($logsFile);

        $first = Shell::launchInDirectory('exec php do routine:launch', $storage->getRoot());
        for ($i = 0; $i < 50 && !str_contains(substr($this->readLogs($logsFile), strlen($logsBefore)), 'SlowReport started'); $i++)
            usleep(100_000);

        $second = Shell::executeInDirectory('php do routine:launch', $storage->getRoot());
        $first->wait();

        $this->assertEquals(0, $second->getExitCode(), $second->getOutput().$second->getErrorOutput());

        $logs = substr($this->readLogs($logsFile), strlen($logsBefore));
        $this->assertEquals(1, substr_count($logs, 'SlowReport started'));
        $this->assertStringContainsString('Skipped App\\Routines\\SlowReport', $logs);
        $this->assertEquals(2, substr_count($logs, 'Heartbeat ping'), 'Only the busy task is skipped');
    }

    protected function readLogs(string $file): string
    {
        return is_file($file) ? file_get_contents($file) : '';
    }
}
