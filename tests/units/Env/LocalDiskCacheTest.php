<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class LocalDiskCacheTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('local-disk-cache-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    /** child() re-initializes the shared driver, detaching references taken on the parent. */
    public function testAChildCacheKeepsTheReferencesOfItsParent()
    {
        $driver = new LocalDiskCache($this->storage);
        $cache = new Cache(new CacheConfiguration($driver));

        $cache->set('invoices', ['first']);
        $driver->save();

        $invoices = &$cache->getReference('invoices', []);
        $invoices[] = 'second';

        $cache->child('sub');

        $this->assertEquals(['first', 'second'], $cache->get('invoices'));
    }

    /** An expired element stays readable for the whole life of the process. */
    public function testAnExpiredElementIsNotServedFromMemory()
    {
        $cache = new Cache(new CacheConfiguration(new LocalDiskCache($this->storage)));

        $cache->set('invoices', 'stale', Cache::SECOND, time() - 10);

        $this->assertFalse($cache->has('invoices'));
        $this->assertNull($cache->get('invoices'));
    }

    /** A key holding a slash names a file in a missing directory, so it is never persisted. */
    public function testAKeyHoldingASlashSurvivesAReload()
    {
        $driver = new LocalDiskCache($this->storage);
        $driver->set('user/42', 'preferences');
        $driver->save();

        $reloaded = new LocalDiskCache($this->storage);
        $reloaded->initialize();

        $this->assertEquals('preferences', $reloaded->get('user/42'));
    }

    public function testAnElementNobodyReadsIsNeitherLoadedNorRewritten()
    {
        $this->storage->write('0_0_broken-invoices', 'not a serialized value');

        $driver = new LocalDiskCache($this->storage);
        $driver->initialize();
        $driver->set('invoices', ['first']);
        $driver->save();

        $this->assertEquals('not a serialized value', file_get_contents($this->storage->path('0_0_broken-invoices')));
    }

    public function testAnElementRemovedByAnotherProcessIsMissing()
    {
        $driver = new LocalDiskCache($this->storage);
        $driver->set('invoices', ['first']);
        $driver->save();

        $reloaded = new LocalDiskCache($this->storage);
        $reloaded->initialize();
        $driver->clear();

        $this->assertFalse($reloaded->has('invoices'));
        $this->assertNull($reloaded->get('invoices'));
    }

    public function testAnElementReadThenChangedThroughItsReferenceIsSaved()
    {
        $driver = new LocalDiskCache($this->storage);
        $driver->set('invoices', ['first']);
        $driver->save();

        $reloaded = new LocalDiskCache($this->storage);
        $reloaded->initialize();
        $cache = new Cache(new CacheConfiguration($reloaded));
        $invoices = &$cache->getReference('invoices', []);
        $invoices[] = 'second';
        $reloaded->save();

        $fresh = new LocalDiskCache($this->storage);
        $fresh->initialize();

        $this->assertEquals(['first', 'second'], $fresh->get('invoices'));
    }

    /** get() falls back on the default when the stored value is null. */
    public function testAStoredNullIsNotReplacedByTheDefault()
    {
        $cache = new Cache(new CacheConfiguration(new LocalDiskCache($this->storage)));

        $cache->set('discount', null);

        $this->assertTrue($cache->has('discount'));
        $this->assertNull($cache->get('discount', 'default'));
    }
}
