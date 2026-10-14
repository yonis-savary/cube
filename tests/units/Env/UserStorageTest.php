<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Storage;
use Cube\Tests\Units\Env\Classes\InvoiceStorage;
use Cube\Tests\Units\Env\Classes\RecordingDiskDriver;
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

    /** The user storage was rebuilt on a LocalDiskDriver, whatever driver the global Storage used. */
    public function testAUserStorageKeepsTheDriverOfTheGlobalStorage()
    {
        $driver = new RecordingDiskDriver();
        $storage = new Storage(Storage::getInstance()->getRoot(), $driver);

        $alice = Storage::withInstance($storage, fn () => InvoiceStorage::getUserInstance(1, md5(serialize(1))));
        $driver->madeDirectories = [];

        $alice->makeDirectory('invoices');

        $this->assertEquals([$alice->path('invoices')], $driver->madeDirectories);

        rmdir($alice->path('invoices'));
        rmdir($alice->getRoot());
    }
}
