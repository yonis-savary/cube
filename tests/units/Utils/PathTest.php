<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;

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
}
