<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ArgsTest extends TestCase
{
    public function testConstructAndGetValue()
    {
        $args = Args::fromArgv(['text.csv', '-a', 'file.php', '--append', 'file-2.php', '-s', '--short', '-i=file-3.txt', 'another', '--input=file-4.txt']);

        $this->assertEquals([
            null => ['text.csv', 'another'],
            '-a' => ['file.php'],
            '--append' => ['file-2.php'],
            '-s' => [],
            '--short' => [],
            '-i' => ['file-3.txt'],
            '--input' => ['file-4.txt'],
        ], $args->dump());

        $this->assertEquals(['file.php'], $args->getValues('a'));
        $this->assertEquals(['file.php'], $args->getValues('-a'));
        $this->assertEquals(['file-2.php'], $args->getValues(null, 'append'));
        $this->assertEquals(['file-2.php'], $args->getValues(null, '--append'));
        $this->assertEquals(['file.php', 'file-2.php'], $args->getValues('a', 'append'));
        $this->assertEquals(['file.php', 'file-2.php'], $args->getValues('-a', '--append'));
        $this->assertEquals(['file.php', 'file-2.php'], $args->getValues('-a', 'append'));
        $this->assertEquals(['file.php', 'file-2.php'], $args->getValues('a', '--append'));

        $this->assertEquals('file.php', $args->getValue('a'));
        $this->assertEquals('file.php', $args->getValue('-a'));
        $this->assertEquals('file-2.php', $args->getValue(null, 'append'));
        $this->assertEquals('file-2.php', $args->getValue(null, '--append'));
        $this->assertEquals('file.php', $args->getValue('a', 'append'));
        $this->assertEquals('file.php', $args->getValue('-a', '--append'));
        $this->assertEquals('file.php', $args->getValue('-a', 'append'));
        $this->assertEquals('file.php', $args->getValue('a', '--append'));

        $this->assertTrue($args->has('-s'));
        $this->assertTrue($args->has('-s', '--short'));
        $this->assertTrue($args->has(null, '--short'));
    }

    public function testEmptyArgv()
    {
        $args = Args::fromArgv([]);

        $this->assertEquals([], $args->dump());
        $this->assertEquals([], $args->getValues());
        $this->assertNull($args->getValue());
        $this->assertFalse($args->has('-a', '--append'));
    }

    public function testValuesWithoutParameterAreGroupedTogether()
    {
        $args = Args::fromArgv(['first.csv', 'second.csv']);

        $this->assertEquals(['first.csv', 'second.csv'], $args->getValues());
        $this->assertEquals('first.csv', $args->getValue());
    }

    /**
     * Deliberate divergence from getopt : where GNU gives one value to an option and makes the
     * rest positional, Cube keeps every value that follows, which is what makes getValues() a
     * list. `-f a b` and `-f a -f b` therefore mean the same thing.
     */
    public function testAParameterKeepsEveryValueThatFollowsIt()
    {
        $args = Args::fromArgv(['-f', 'first.csv', 'second.csv']);

        $this->assertEquals(['first.csv', 'second.csv'], $args->getValues('-f'));
        $this->assertEquals('first.csv', $args->getValue('-f'));
    }

    public function testTheSameParameterCanBeRepeated()
    {
        $args = Args::fromArgv(['-f', 'first.csv', '-f', 'second.csv']);

        $this->assertEquals(['first.csv', 'second.csv'], $args->getValues('-f'));
    }

    public function testAnInlineValueClosesItsParameter()
    {
        $args = Args::fromArgv(['-f=first.csv', 'second.csv']);

        $this->assertEquals(['first.csv'], $args->getValues('-f'));
        $this->assertEquals(['second.csv'], $args->getValues());
    }

    public function testAFlagHasNoValue()
    {
        $args = Args::fromArgv(['-s']);

        $this->assertTrue($args->has('-s'));
        $this->assertEquals([], $args->getValues('-s'));
        $this->assertNull($args->getValue('-s'));
    }

    public function testGetValueFallsBackOnItsDefault()
    {
        $args = Args::fromArgv(['-s']);

        $this->assertEquals('8000', $args->getValue('-p', '--port', '8000'));
        $this->assertEquals('8000', $args->getValue('-s', '--short', '8000'));
    }

    public function testHasIsFalseForAnUnknownParameter()
    {
        $args = Args::fromArgv(['-a', 'file.php']);

        $this->assertFalse($args->has('-b', '--because'));
        $this->assertTrue($args->has('a'));
    }

    public function testAddParameterAndAddValue()
    {
        $args = new Args();
        $args->addParameter('-s');

        $this->assertSame($args, $args->addValue('-f', 'first.csv'));

        $this->assertEquals(['-s' => [], '-f' => ['first.csv']], $args->dump());
    }

    public function testAddParameterKeepsTheValuesAlreadyGiven()
    {
        $args = new Args();
        $args->addValue('-f', 'first.csv');
        $args->addParameter('-f');

        $this->assertEquals(['first.csv'], $args->getValues('-f'));
    }

    public function testToStringPrintsAParameterWithItsValues()
    {
        $args = Args::fromArgv(['-f', 'first.csv', 'second.csv']);

        $this->assertEquals('-f first.csv second.csv', $args->toString());
    }

    public function testToStringGivesTheCommandLineBack()
    {
        $argv = ['-n', 'some-custom-name', '-f', 'file1', 'file2', '--file', 'file3'];

        $this->assertEquals(join(' ', $argv), Args::fromArgv($argv)->toString());
    }

    public function testToStringKeepsTheValuesWithoutParameter()
    {
        $args = Args::fromArgv(['first.csv', '-f', 'second.csv']);

        $this->assertEquals('first.csv -f second.csv', $args->toString());
    }

    public function testAnInlineValueIsSplitOnItsFirstEqualSignOnly()
    {
        $args = Args::fromArgv(['--url=http://cube.test/?page=2', '--path=C:=/tmp']);

        $this->assertEquals('http://cube.test/?page=2', $args->getValue(null, '--url'));
        $this->assertEquals('C:=/tmp', $args->getValue(null, '--path'));
    }

    public function testAnInlineValueCanBeEmpty()
    {
        $args = Args::fromArgv(['--name=']);

        $this->assertTrue($args->has(null, '--name'));
        $this->assertEquals('', $args->getValue(null, '--name'));
    }

    public function testAnInlineValueCanBeNegative()
    {
        $args = Args::fromArgv(['--offset=-5']);

        $this->assertEquals('-5', $args->getValue(null, '--offset'));
    }

    public function testADoubleDashEndsTheParameters()
    {
        $args = Args::fromArgv(['-f', 'first.csv', '--', '--not-a-parameter', '-n']);

        $this->assertEquals(['first.csv'], $args->getValues('-f'));
        $this->assertEquals(['--not-a-parameter', '-n'], $args->getValues());
        $this->assertFalse($args->has(null, '--not-a-parameter'));
        $this->assertFalse($args->has('-n'));
    }

    public function testADoubleDashIsNotAParameterItself()
    {
        $args = Args::fromArgv(['--', 'first.csv']);

        $this->assertEquals([null => ['first.csv']], $args->dump());
    }

    public function testALoneDashIsAValue()
    {
        $args = Args::fromArgv(['-']);

        $this->assertEquals(['-'], $args->getValues());
        $this->assertFalse($args->has('-'));
    }

    public function testALoneDashCanBeTheValueOfAParameter()
    {
        $args = Args::fromArgv(['-f', '-']);

        $this->assertEquals(['-'], $args->getValues('-f'));
    }

    /**
     * Bundling ('-abc' standing for '-a -b -c') needs the parser to know which options take a
     * value, to tell '-a -b -c' from '-a bc'. Args parses without any declaration, so a
     * multi-letter short parameter stays one parameter.
     */
    public function testShortParametersAreNotBundled()
    {
        $args = Args::fromArgv(['-abc']);

        $this->assertTrue($args->has('-abc'));
        $this->assertFalse($args->has('-a'));
    }
}
