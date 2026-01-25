<?php

namespace Tests\Integration;

use App\Models\ModelTrait;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

/**
 * Assert that as we execute models:generate on our integration app
 * Custom model properties such as trait and methods are kept
 */
class ModelGenerationTest extends TestCase
{
    public function testCustomMethodsAreKept() {
        $this->assertTrue(method_exists(Product::class, 'getPriceWithTax'));
    }

    public function testTraitUseAreKept() {
        $this->assertContains(ModelTrait::class, class_uses(Product::class));
    }
}