<?php

namespace Cube\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Cube\Utils\Shell;

class InstallationTest extends TestCase
{
    public function test_installation()
    {
        $storage = Utils::getDummyApplicationStorage();

        $this->assertTrue($storage->isDirectory("vendor/yonis-savary/cube"));
        $this->assertTrue($storage->isFile("do"));

        $proc = Shell::executeInDirectory("php do hello-world", $storage->getRoot());
        $this->assertStringStartsWith("Hello World !", $proc->getOutput());

    }
}