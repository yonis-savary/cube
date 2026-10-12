<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class PathTest extends TestCase
{
    public function testNormalize()
    {
        foreach ([
            '\Users\Foo' => '/Users/Foo',
            '/users/' => '/users',
            '\\\Users\\' => '/Users',
            '\users//foo' => '/users/foo',
        ] as $input => $expected) {
            $this->assertEquals($expected, Path::normalize($input));
        }
    }

    public function testJoin()
    {
        $this->assertEquals('/user/foo', Path::join('/user/', '\foo'));
        $this->assertEquals('/user/foo', Path::join('/user', '\foo/'));
        $this->assertEquals('/user/foo', Path::join('/user', 'foo'));
    }

    public function testRelative()
    {
        $this->assertEquals('/home/foo/a.txt', Path::relative('a.txt', '/home/foo'));
        $this->assertEquals('/home/foo/a.txt', Path::relative('\home\foo\a.txt', '/home/foo'));
    }

    public function testToRelative()
    {
        $this->assertEquals('a.txt', Path::toRelative('/home/foo/a.txt', '/home/foo'));
        $this->assertEquals('a.txt', Path::toRelative('/home/foo/a.txt', '/home/foo/'));
        $this->assertEquals('.config/a.txt', Path::toRelative('\home\foo\.config\a.txt', '/home/foo'));
    }

    /** join() drops its parts through array_filter(), which also removes a part named "0". */
    public function testJoinKeepsAPartNamedZero()
    {
        $this->assertEquals('/data/0', Path::join('/data', '0'));
        $this->assertEquals('/data/0', Path::relative('0', '/data'));
    }

    /** relative() checks the reference with a bare string prefix, without a directory boundary. */
    public function testRelativeOnlyLeavesAPathUnderTheReferenceAlone()
    {
        $this->assertEquals('/data/app/data/application.log', Path::relative('/data/application.log', '/data/app'));
    }

    /** The PSR-4 lookup compares against arrays of directories and the fallback regexes have no capture groups. */
    public function testPathToNamespaceFollowsThePsr4Prefixes()
    {
        $this->assertEquals('Cube\\Tests\\Units\\Utils', Path::pathToNamespace(Path::relative('tests/units/Utils')));
        $this->assertEquals('App', Path::pathToNamespace(Path::relative('tests/integration-root/App')));
    }

    public function testConfinedResolvesDotSegmentsInsideTheReference()
    {
        $this->assertEquals('/data/b.txt', Path::confined('a/../b.txt', '/data'));
        $this->assertEquals('/data/outside.txt', Path::confined('../../outside.txt', '/data'));
        $this->assertEquals('/data/etc/passwd', Path::confined('/data/../etc/passwd', '/data'));
        $this->assertEquals('/data', Path::confined('/', '/data'));
    }

    public function testPathToNamespaceOutsideOfThePsr4PrefixesPascalCasesEachDirectory()
    {
        $this->assertEquals('Storage\\MyModule\\SubDirectory', Path::pathToNamespace(Path::relative('Storage/my-module/sub_directory')));
    }

    /** chdir('..') never fails at `/`, so a directory outside any Cube project made the lookup loop forever. */
    public function testResolvingTheProjectOutsideOfAnyProjectThrows()
    {
        $process = new Process([
            PHP_BINARY, '-r',
            'require $argv[1]; try { Cube\Utils\Path::resolveProjectPath(); } catch (RuntimeException $e) { echo $e->getMessage(); }',
            realpath('vendor/autoload.php'),
        ], sys_get_temp_dir(), timeout: 10);
        $process->run();

        $this->assertStringStartsWith('Could not find the project root', $process->getOutput(), $process->getErrorOutput());
    }
}
