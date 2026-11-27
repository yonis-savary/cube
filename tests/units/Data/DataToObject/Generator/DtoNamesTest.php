<?php

namespace Cube\Tests\Units\Data\DataToObject\Generator;

use Cube\Data\DataToObject\Generator\DtoNames;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DtoNamesTest extends TestCase
{
    public function testAReservedWordIsSuffixed()
    {
        $this->assertEquals('ListDto', DtoNames::className('list'));
    }

    /** PHP 8.6 deprecates `let` and `is` as class names. */
    public function testLetAndIsAreSuffixed()
    {
        $this->assertEquals('LetDto', DtoNames::className('let'));
        $this->assertEquals('IsDto', DtoNames::className('is'));
    }

    public function testAnOrdinaryNameIsKept()
    {
        $this->assertEquals('Island', DtoNames::className('island'));
    }
}
