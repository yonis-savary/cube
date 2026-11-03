<?php

namespace Cube\Tests\Units\Data;

use Cube\Data\AutoDataToObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AutoDataToObjectTest extends TestCase
{
    /** `(array) $this` exported protected and private properties, under mangled keys. */
    public function testOnlyPublicPropertiesAreExported()
    {
        $webhook = new class('product.created') extends AutoDataToObject {
            protected string $secret = 'signing-key';
            private int $attempts = 3;

            public function __construct(public string $event) {}
        };

        $this->assertSame(['event' => 'product.created'], $webhook->toArray());
    }

    /** Nested objects were only converted inside lists, and kept as objects inside associative arrays. */
    public function testNestedObjectsAreConvertedInsideAssociativeArrays()
    {
        $tag = new class('sale') extends AutoDataToObject {
            public function __construct(public string $label) {}
        };

        $webhook = new class(['primary' => $tag, 'all' => [$tag]]) extends AutoDataToObject {
            public function __construct(public array $tags) {}
        };

        $this->assertSame(
            ['tags' => ['primary' => ['label' => 'sale'], 'all' => [['label' => 'sale']]]],
            $webhook->toArray()
        );
    }

    public function testKeysRenameTheExportedProperties()
    {
        $line = new class(3, ['size' => 'L']) extends AutoDataToObject {
            public ?string $comment;

            public function __construct(public int $unitCount, public array $variantOptions) {}

            public static function keys(): array
            {
                return ['unitCount' => 'unit-count', 'variantOptions' => 'variant_options'];
            }

            protected function objectKeys(): array
            {
                return ['variantOptions'];
            }
        };

        $this->assertSame(['unit-count' => 3, 'variant_options' => ['size' => 'L']], $line->toArray());
    }

    public function testAnUninitializedPropertyIsLeftOutWhileANullOneIsKept()
    {
        $patch = new class extends AutoDataToObject {
            public ?string $name;
            public ?string $comment;
        };

        $patch->comment = null;

        $this->assertSame(['comment' => null], $patch->toArray());
    }
}
