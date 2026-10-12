<?php

namespace Cube\Tests\Units\Env;

use Cube\Tests\Units\Env\Classes\InvoiceStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
class UserStorageTest extends TestCase
{
    public function testEachUserGetsTheirOwnDirectory()
    {
        $alice = InvoiceStorage::getUserInstance(1, md5(serialize(1)));
        $bob = InvoiceStorage::getUserInstance(2, md5(serialize(2)));

        $this->assertNotEquals($alice->getRoot(), $bob->getRoot());

        rmdir($alice->getRoot());
        rmdir($bob->getRoot());
    }

    /** Every anonymous visitor hashed to the same key, so they all read and wrote one shared directory. */
    public function testNobodyLoggedInGetsNoDirectory()
    {
        $this->expectException(RuntimeException::class);

        InvoiceStorage::getUserInstance(false, md5(serialize(false)));
    }
}
