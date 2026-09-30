<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\Models\DummyModel;
use Cube\Tests\Units\Models\Product;
use PHPUnit\Framework\TestCase;

class DummyModelTest extends TestCase
{
    public function testReadingAnUnknownAttributeGivesNull()
    {
        $this->assertNull((new DummyModel())->anything);
    }

    public function testWritingAnUnknownAttributeKeepsIt()
    {
        $model = new DummyModel();
        $model->manager = 'alice';

        $this->assertEquals('alice', $model->manager);
    }

    public function testRegularModelStillRejectsAnUnknownAttribute()
    {
        $this->expectException(\RuntimeException::class);
        (new Product())->anything;
    }
}
