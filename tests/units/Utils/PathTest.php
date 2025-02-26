<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Path;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class PathTest extends TestCase
{
    public function testNormalize()
    {
        foreach ([
            '\\Users\\Foo' => '/Users/Foo',
            '/users/' => '/users',
            '\\\\Users\\' => '/Users',
            '\\users//foo' => '/users/foo',
        ] as $input => $expected) {
            $this->assertEquals($expected, Path::normalize($input));
        }
    }

    public function testJoin()
    {
        $this->assertEquals('/user/foo', Path::join('/user/', '\\foo'));
        $this->assertEquals('/user/foo', Path::join('/user', '\\foo/'));
        $this->assertEquals('/user/foo', Path::join('/user', 'foo'));
    }

    public function testRelative()
    {
        $this->assertEquals('/home/foo/a.txt', Path::relative('a.txt', '/home/foo'));
        $this->assertEquals('/home/foo/a.txt', Path::relative('\\home\\foo\\a.txt', '/home/foo'));
    }

    public function testToRelative()
    {
        $this->assertEquals('a.txt', Path::toRelative('/home/foo/a.txt', '/home/foo'));
        $this->assertEquals('a.txt', Path::toRelative('/home/foo/a.txt', '/home/foo/'));
        $this->assertEquals('.config/a.txt', Path::toRelative('\\home\\foo\\.config\\a.txt', '/home/foo'));
    }
}
