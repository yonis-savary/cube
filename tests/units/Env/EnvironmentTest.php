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

        unlink($sampleFile);
    }

    public function testEnvironmentStartsFromTheProcessEnvironment() {
        $_ENV['CUBE_TEST_VARIABLE'] = 'from-the-process';

        $env = new Environment();

        $this->assertEquals('from-the-process', $env->get('CUBE_TEST_VARIABLE'));

        unset($_ENV['CUBE_TEST_VARIABLE']);
    }

    public function testFileValuesOverrideTheProcessOnes() {
        $_ENV['CUBE_TEST_VARIABLE'] = 'from-the-process';

        $sampleFile = $this->writeEnvironmentFile("CUBE_TEST_VARIABLE=from-the-file");
        $env = (new Environment())->mergeWithFile($sampleFile);

        $this->assertEquals('from-the-file', $env->get('CUBE_TEST_VARIABLE'));

        unset($_ENV['CUBE_TEST_VARIABLE']);
        unlink($sampleFile);
    }

    public function testGetFallsBackOnTheGivenDefault() {
        $env = new Environment();

        $this->assertNull($env->get('CUBE_UNKNOWN_VARIABLE'));
        $this->assertEquals('fallback', $env->get('CUBE_UNKNOWN_VARIABLE', 'fallback'));
    }

    public function testSetOverridesAValue() {
        $env = new Environment();
        $env->set('CUBE_TEST_VARIABLE', 'first');
        $env->set('CUBE_TEST_VARIABLE', 'second');

        $this->assertEquals('second', $env->get('CUBE_TEST_VARIABLE'));
    }

    public function testAMissingFileIsIgnored() {
        $env = new Environment();

        $this->assertSame($env, $env->mergeWithFile(uniqid('no-such-file-').'.env'));
        $this->assertNull($env->get('CUBE_TEST_VARIABLE'));
    }

    public function testAnUnparsableFileIsLeftOut() {
        $sampleFile = $this->writeEnvironmentFile("A=1\n[unclosed-section\n");

        $env = new Environment();

        // parse_ini_string() reports the syntax error through a PHP warning, which PHPUnit
        // turns into a failure : what is under test is the returned environment
        set_error_handler(fn () => true);

        try {
            $env->mergeWithFile($sampleFile);
        } finally {
            restore_error_handler();
        }

        $this->assertNull($env->get('A'));

        unlink($sampleFile);
    }

    protected function writeEnvironmentFile(string $content): string {
        $file = Storage::getInstance()->path(uniqid('environment-') . '.env');
        file_put_contents($file, $content);

        return $file;
    }
}
