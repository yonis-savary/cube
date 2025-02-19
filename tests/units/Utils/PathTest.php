<?php

namespace Cube\Tests\Units\Utils;

use PHPUnit\Framework\TestCase;
use Cube\Utils\Path;

class PathTest extends TestCase
{
    public function test_normalize()
    {
        foreach ([
            "\\Users\\Foo" => "/Users/Foo",
            "/users/" => "/users",
            "\\\\Users\\" => "/Users",
            "\\users//foo" => "/users/foo"
        ] as $input => $expected)
            $this->assertEquals($expected, Path::normalize($input));
    }

    public function test_join()
    {
        $this->assertEquals("/user/foo", Path::join("/user/", "\\foo"));
        $this->assertEquals("/user/foo", Path::join("/user", "\\foo/"));
        $this->assertEquals("/user/foo", Path::join("/user", "foo"));
    }

    public function test_relative()
    {
        $this->assertEquals("/home/foo/a.txt", Path::relative("a.txt", "/home/foo"));
        $this->assertEquals("/home/foo/a.txt", Path::relative("\\home\\foo\\a.txt", "/home/foo"));
    }

    public function test_toRelative()
    {
        $this->assertEquals("a.txt", Path::toRelative("/home/foo/a.txt", "/home/foo"));
        $this->assertEquals("a.txt", Path::toRelative("/home/foo/a.txt", "/home/foo/"));
        $this->assertEquals(".config/a.txt", Path::toRelative("\\home\\foo\\.config\\a.txt", "/home/foo"));
    }
}

