<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Text;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class TextTest extends TestCase
{
    public function testEndsWith()
    {
        $this->assertEquals('000111000', Text::endsWith('000111000', '000'));
        $this->assertEquals('000111000', Text::endsWith('000111', '000'));
    }

    public function testDontEndsWith()
    {
        $this->assertEquals('000111', Text::dontEndsWith('000111', '000'));
        $this->assertEquals('000111', Text::dontEndsWith('000111000', '000'));
    }

    public function testStartsWith()
    {
        $this->assertEquals('000111000', Text::startsWith('000111000', '000'));
        $this->assertEquals('000111000', Text::startsWith('111000', '000'));
    }

    public function testDontStartsWith()
    {
        $this->assertEquals('111000', Text::dontStartsWith('111000', '000'));
        $this->assertEquals('111000', Text::dontStartsWith('000111000', '000'));
    }
}
