<?php

namespace Cube\Tests\Units\Utils;

use PHPUnit\Framework\TestCase;
use Cube\Utils\Text;

class TextTest extends TestCase
{
    public function test_endsWith()
    {
        $this->assertEquals("000111000", Text::endsWith("000111000", "000"));
        $this->assertEquals("000111000", Text::endsWith("000111", "000"));
    }

    public function test_dontEndsWith()
    {
        $this->assertEquals("000111", Text::dontEndsWith("000111", "000"));
        $this->assertEquals("000111", Text::dontEndsWith("000111000", "000"));
    }

    public function test_startsWith()
    {
        $this->assertEquals("000111000", Text::startsWith("000111000", "000"));
        $this->assertEquals("000111000", Text::startsWith("111000", "000"));
    }

    public function test_dontStartsWith()
    {
        $this->assertEquals("111000", Text::dontStartsWith("111000", "000"));
        $this->assertEquals("111000", Text::dontStartsWith("000111000", "000"));
    }

}