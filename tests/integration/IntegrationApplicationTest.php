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

        $proc = Shell::launchInDirectory('php do cube:queue --queue=DisplayerQueue', $storage->getRoot());
        sleep(3);
        $proc->stop();

        $logsFile = $storage->path("Storage/Logs/displayerqueue.csv");
        $this->assertFileExists($logsFile);

        $logs = file_get_contents($logsFile);
        $this->assertStringContainsString("DISPLAY : 0", $logs);
        $this->assertStringContainsString("DISPLAY : 29", $logs);
    }
}