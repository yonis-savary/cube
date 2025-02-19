<?php

namespace Cube\Tests\Units\Console;

use PHPUnit\Framework\TestCase;
use Cube\Console\Args;

class ArgsTest extends TestCase
{
    public function test_construct_and_get_value()
    {
        $args = Args::fromArgv(["text.csv", "-a", "file.php", "--append", "file-2.php", "-s", "--short", "-i=file-3.txt", "another", "--input=file-4.txt"]);

        $this->assertEquals([
            null => ["text.csv", "another"],
            "-a" => ["file.php"],
            "--append" => ["file-2.php"],
            "-s" => [],
            "--short" => [],
            "-i" => ["file-3.txt"],
            "--input" => ["file-4.txt"]
        ], $args->dump());


        $this->assertEquals(["file.php"], $args->getValues("a"));
        $this->assertEquals(["file.php"], $args->getValues("-a"));
        $this->assertEquals(["file-2.php"], $args->getValues(null, "append"));
        $this->assertEquals(["file-2.php"], $args->getValues(null, "--append"));
        $this->assertEquals(["file.php", "file-2.php"], $args->getValues("a", "append"));
        $this->assertEquals(["file.php", "file-2.php"], $args->getValues("-a", "--append"));
        $this->assertEquals(["file.php", "file-2.php"], $args->getValues("-a", "append"));
        $this->assertEquals(["file.php", "file-2.php"], $args->getValues("a", "--append"));

        $this->assertEquals("file.php", $args->getValue("a"));
        $this->assertEquals("file.php", $args->getValue("-a"));
        $this->assertEquals("file-2.php", $args->getValue(null, "append"));
        $this->assertEquals("file-2.php", $args->getValue(null, "--append"));
        $this->assertEquals("file.php", $args->getValue("a", "append"));
        $this->assertEquals("file.php", $args->getValue("-a", "--append"));
        $this->assertEquals("file.php", $args->getValue("-a", "append"));
        $this->assertEquals("file.php", $args->getValue("a", "--append"));

        $this->assertTrue($args->has("-s"));
        $this->assertTrue($args->has("-s", "--short"));
        $this->assertTrue($args->has(null, "--short"));
    }
}