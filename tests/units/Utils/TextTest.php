<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Text;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TextTest extends TestCase
{
    public function testEndsWith()
    {
        $this->assertEquals('000111000', Text::endsWith('000111000', '000'));
        $this->assertEquals('000111000', Text::endsWith('000111', '000'));
    }

    public function testRightWordTrim()
    {
        $this->assertEquals('000111', Text::rightWordTrim('000111', '000'));
        $this->assertEquals('000111', Text::rightWordTrim('000111000', '000'));
    }

    public function testStartsWith()
    {
        $this->assertEquals('000111000', Text::startsWith('000111000', '000'));
        $this->assertEquals('000111000', Text::startsWith('111000', '000'));
    }

    public function testLeftWordTrim()
    {
        $this->assertEquals('111000', Text::leftWordTrim('111000', '000'));
        $this->assertEquals('111000', Text::leftWordTrim('000111000', '000'));
    }

    /** Each placeholder is replaced in turn, so a value holding a placeholder gets expanded too. */
    public function testInterpolateDoesNotExpandPlaceholdersFoundInValues()
    {
        $this->assertEquals(
            'Customer {email} ordered secret',
            Text::interpolate('Customer {name} ordered {item}', ['name' => '{email}', 'email' => 'secret', 'item' => 'secret'])
        );
    }

    /** An empty suffix or prefix is always found, so the loop removing it never ended. */
    public function testAnEmptyAffixIsLeftAlone()
    {
        $this->assertEquals('invoice', Text::rightWordTrim('invoice', ''));
        $this->assertEquals('invoice', Text::leftWordTrim('invoice', ''));
    }
}
