<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

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
    public function test_a_child_cache_keeps_the_references_of_its_parent()
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
    public function test_an_expired_element_is_not_served_from_memory()
    {
        $cache = new Cache(new CacheConfiguration(new LocalDiskCache($this->storage)));

        $cache->set('invoices', 'stale', Cache::SECOND, time() - 10);

        $this->assertFalse($cache->has('invoices'));
        $this->assertNull($cache->get('invoices'));
    }

    /** A key holding a slash names a file in a missing directory, so it is never persisted. */
    public function test_a_key_holding_a_slash_survives_a_reload()
    {
        $driver = new LocalDiskCache($this->storage);
        $driver->set('user/42', 'preferences');
        $driver->save();

        $reloaded = new LocalDiskCache($this->storage);
        $reloaded->initialize();

        $this->assertEquals('preferences', $reloaded->get('user/42'));
    }

    /** get() falls back on the default when the stored value is null. */
    public function test_a_stored_null_is_not_replaced_by_the_default()
    {
        $cache = new Cache(new CacheConfiguration(new LocalDiskCache($this->storage)));

        $cache->set('discount', null);

        $this->assertTrue($cache->has('discount'));
        $this->assertNull($cache->get('discount', 'default'));
    }
}
