<?php

namespace Cube\Tests\Units\Utils;

use Cube\Utils\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class UtilsTest extends TestCase
{
    public function testIsAssoc()
    {
        $this->assertTrue(Utils::isAssoc(['A' => 0]));
        $this->assertFalse(Utils::isAssoc([0 => 'A', 1 => 'B']));
        $this->assertFalse(Utils::isAssoc(['A', 'B']));
        $this->assertTrue(Utils::isAssoc([]));
        $this->assertFalse(Utils::isAssoc([], true));
    }

    public function testIsList()
    {
        $this->assertFalse(Utils::isList(['A' => 0]));
        $this->assertTrue(Utils::isList([0 => 'A', 1 => 'B']));
        $this->assertTrue(Utils::isList(['A', 'B']));
        $this->assertTrue(Utils::isList([]));
        $this->assertFalse(Utils::isList([], false));
    }
}
