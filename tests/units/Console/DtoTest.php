<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Commands\DataToObject\Classes\DataToObjectGenClass;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DtoTest extends TestCase
{
    use HasTemporaryStorage;

    /** An ISO date generated a `?\Datetime` parameter, which fromData() then filled with the raw string : TypeError. */
    public function testAnIsoDateIsGeneratedAsAString()
    {
        $this->setUpTemporaryStorage('dto-test-');

        try {
            (new DataToObjectGenClass('Invoice', ['created_at' => '2025-02-03T08:47:06.000Z']))
                ->generateInto($this->storage);

            $generated = $this->storage->read('Invoice.php');

            $this->assertStringContainsString('public ?string $createdAt', $generated);
            $this->assertStringNotContainsStringIgnoringCase('datetime', $generated);
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }
}
