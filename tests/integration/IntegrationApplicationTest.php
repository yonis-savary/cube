<?php

namespace Cube\Tests\Integration;

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
        $this->assertEquals(0, $proc->getExitCode(), $proc->getOutput());
    }
}