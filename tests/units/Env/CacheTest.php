<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Cache\RedisCache\RedisCache;
use Cube\Env\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    /** @var Cache[] */
    protected static array $temporaryCaches = [];

    /** @var Storage[] */
    protected static array $temporaryStorages = [];

    /**
     * @return Cache[]
     */
    public static function getCaches(): array {
        return [
            'local disk cache' => [self::newLocalDiskCache()],
            'redis cache' => [self::remember(new Cache(new CacheConfiguration(new RedisCache(uniqid('cube-test-'), '127.0.0.1', 6379))))]
        ];
    }

    /**
     * A cache outlives the test that used it (the data provider builds them all upfront),
     * so emptying them here is what keeps a disk cache from feeding the next run.
     */
    public static function tearDownAfterClass(): void {
        foreach (self::$temporaryCaches as $cache)
            $cache->clear();

        foreach (self::$temporaryStorages as $storage)
            rmdir($storage->getRoot());

        self::$temporaryCaches = [];
        self::$temporaryStorages = [];
    }

    #[DataProvider('getCaches')]
    public function testSetAndGet(Cache $cache) {
        $this->assertNull($cache->get('some-key'));
        $this->assertEquals('some-default', $cache->get('some-key', 'some-default'));

        $myObject = (object) [
            'some-object-key' => 'uninspired description'
        ];

        $cache->set('some-key', $myObject);

        $this->assertEquals($myObject, $cache->get('some-key'));
    }

    #[DataProvider('getCaches')]
    public function testGetOrSet(Cache $cache) {

        $executionCount = 0;

        $this->assertFalse($cache->has('some-key'));
        $result = $cache->getOrSet("some-key", function() use (&$executionCount) {
            $executionCount++;
            return 1+2;
        });

        $this->assertEquals(3, $result);
        $this->assertEquals(1, $executionCount);
        $this->assertEquals(3, $cache->get('some-key'));

        $result = $cache->getOrSet("some-key", function() use (&$executionCount) {
            $executionCount++;
            return 10+20;
        });

        $this->assertEquals(3, $result); // should not set it again
    }

    #[DataProvider('getCaches')]
    public function testDelete(Cache $cache) {
        $cache->set('some-key', 'hello!');
        $this->assertEquals('hello!', $cache->get('some-key'));

        $cache->delete('some-key');
        $this->assertNull($cache->get('some-key'));
    }

    #[DataProvider('getCaches')]
    public function testClear(Cache $cache) {
        $cache->set('first-key', 'first-value');
        $cache->set('second-key', 'second-value');

        $this->assertTrue($cache->has('first-key'));
        $this->assertTrue($cache->has('second-key'));

        $cache->clear();

        $this->assertFalse($cache->has('first-key'));
        $this->assertFalse($cache->has('second-key'));
    }


    #[DataProvider('getCaches')]
    public function testCallbackSet(Cache $cache) {
        $cache->set('some-key', fn() => 1+2);
        $this->assertEquals(3, $cache->get('some-key'));
    }

    #[DataProvider('getCaches')]
    public function testTry(Cache $cache) {
        $this->assertFalse($cache->try('some-key'));

        $cache->set('some-key', 'hello!');
        $this->assertEquals('hello!', $cache->try('some-key'));
    }

    #[DataProvider('getCaches')]
    public function testChildPrefixesItsKeys(Cache $cache) {
        $child = $cache->child('sub');
        $child->set('some-key', 'hello!');

        $this->assertEquals('hello!', $child->get('some-key'));
        $this->assertEquals('hello!', $cache->get('sub-some-key'));
        $this->assertFalse($cache->has('some-key'));
    }

    #[DataProvider('getCaches')]
    public function testChildPrefixesEveryKeyOnlyOnce(Cache $cache) {
        $child = $cache->child('sub');

        $child->getOrSet('from-get-or-set', 'hello!');
        $child->set('from-set', 'hello!');

        $this->assertEquals('hello!', $child->get('from-get-or-set'));
        $this->assertEquals('hello!', $child->try('from-get-or-set'));
        $this->assertTrue($child->has('from-set'));
        $this->assertFalse($cache->has('sub-sub-from-get-or-set'));
        $this->assertFalse($cache->has('sub-sub-from-set'));
    }

    #[DataProvider('getCaches')]
    public function testChildrenCanBeNested(Cache $cache) {
        $cache->child('sub')->child('subsub')->set('some-key', 'hello!');

        $this->assertEquals('hello!', $cache->get('sub-subsub-some-key'));
    }

    public function testGetReferenceHandsAMutableSlot() {
        // Only the local disk driver keeps a live reference : RedisCache::getReference()
        // warns and gives back a detached copy
        $cache = self::newLocalDiskCache();

        $list = &$cache->getReference('some-list', []);
        $list[] = 'first';

        $this->assertEquals(['first'], $cache->get('some-list'));
    }

    public function testGetReferenceOnAChildStaysOnTheSameKey() {
        $cache = self::newLocalDiskCache();
        $child = $cache->child('sub');

        $list = &$child->getReference('some-list', []);
        $list[] = 'first';

        $this->assertEquals(['first'], $child->get('some-list'));
        $this->assertFalse($cache->has('sub-sub-some-list'));
    }

    protected static function newLocalDiskCache(): Cache {
        $storage = Storage::getInstance()->child(uniqid('cache-test-'));
        self::$temporaryStorages[] = $storage;

        return self::remember(new Cache(new CacheConfiguration(new LocalDiskCache($storage))));
    }

    protected static function remember(Cache $cache): Cache {
        self::$temporaryCaches[] = $cache;

        return $cache;
    }
}
