<?php

namespace Cube\Tests\Integration;

use Cube\Utils\Shell;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InstallationTest extends TestCase
{
    public function testInstallation()
    {
        $storage = Utils::getDummyApplicationStorage();

        $this->assertTrue($storage->isDirectory('vendor/yonis-savary/cube'));
        $this->assertTrue($storage->isFile('do'));

        $proc = Shell::executeInDirectory('php do hello-world', $storage->getRoot());
        $this->assertStringStartsWith('Hello World !', $proc->getOutput());
    }
}
