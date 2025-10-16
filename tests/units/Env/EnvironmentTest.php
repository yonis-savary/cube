<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Environment;
use Cube\Env\Storage;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    public function testMergeWithFileContainingComments() {
        $sampleFile = Storage::getInstance()->path(uniqid() . '.env');
        file_put_contents($sampleFile, join("\n", [
            "# Some Comment Here !",
            "A=1",
            "# Another Comment",
            "# A=2",
            "B=2"
        ]));

        $env = new Environment();
        $env->mergeWithFile($sampleFile);

        $this->assertEquals("1", $env->get("A"));
        $this->assertEquals("2", $env->get("B"));
    }
}