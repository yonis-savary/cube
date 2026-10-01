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

    /** The environment only read $_ENV, which is empty under the production php.ini (variables_order=GPCS). */
    public function testEnvironmentReadsTheProcessVariables() {
        putenv('CUBE_TEST_GETENV=from-getenv');

        try {
            $this->assertEquals('from-getenv', (new Environment())->get('CUBE_TEST_GETENV'));
        } finally {
            putenv('CUBE_TEST_GETENV');
        }
    }

    public function testIniKeywordsKeepTheirFormerValues() {
        $sampleFile = $this->writeEnvironmentFile("DEBUG=false\nCACHED=true\nLEGACY=yes\nNOTHING=null\nCOUNT=42");
        $env = (new Environment())->mergeWithFile($sampleFile);
        unlink($sampleFile);

        $this->assertSame('', $env->get('DEBUG'));
        $this->assertSame('1', $env->get('CACHED'));
        $this->assertSame('1', $env->get('LEGACY'));
        $this->assertSame('', $env->get('NOTHING'));
        $this->assertSame('42', $env->get('COUNT'));
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

    /** parse_ini_string() rejects an unquoted "=" or "!", and the whole file is then left out. */
    public function testValuesHoldingIniOperatorsAreRead() {
        $sampleFile = $this->writeEnvironmentFile("APP_KEY=c2VjcmV0==\nDATABASE_PASSWORD=abc!def\nAPP_NAME=shop");

        set_error_handler(fn () => true);

        try {
            $env = (new Environment())->mergeWithFile($sampleFile);
        } finally {
            restore_error_handler();
            unlink($sampleFile);
        }

        $this->assertEquals('c2VjcmV0==', $env->get('APP_KEY'));
        $this->assertEquals('abc!def', $env->get('DATABASE_PASSWORD'));
        $this->assertEquals('shop', $env->get('APP_NAME'));
    }

    protected function writeEnvironmentFile(string $content): string {
        $file = Storage::getInstance()->path(uniqid('environment-') . '.env');
        file_put_contents($file, $content);

        return $file;
    }
}
