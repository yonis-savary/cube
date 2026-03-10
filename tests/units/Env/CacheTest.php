<?php

namespace Cube\Tests\Units\Env;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Cache\RedisCache\RedisCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    /**
     * @return Cache[]
     */
    public static function getCaches(): array {
        return [
            'local disk cache' => [new Cache(new CacheConfiguration(new LocalDiskCache()))],
            'redis cache' => [new Cache(new CacheConfiguration(new RedisCache(uniqid('cube-test-'), '127.0.0.1', 6379)))]
        ];
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
}