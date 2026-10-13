<?php

namespace Cube\Tests\Units\Events;

use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Event\Events;
use Cube\Event\Events\PostDeployment;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class PostDeploymentTest extends TestCase
{
    use HasTemporaryStorage;

    protected function setUp(): void
    {
        $this->setUpTemporaryStorage('post-deployment-test-');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryStorage();
    }

    /** PostDeployment cleared the whole Cache driver : every entry of the application went with the class index. */
    public function testTheApplicationCacheIsKeptAndTheRoutesAreDropped()
    {
        $driver = new LocalDiskCache($this->storage->child('Cache'));
        $cache = new Cache(new CacheConfiguration($driver));
        $cache->set('exchange-rates', [1.08]);
        $cache->child('router')->set('GET /ping', 'remembered route');

        // setup.php registers its listeners on the Events instance of the moment, which another test may have replaced
        Cache::withInstance($cache, fn () => Events::withInstance(new Events(), function () {
            require __DIR__.'/../../../src/Helpers/setup.php';
            (new PostDeployment())->dispatch();
        }));

        $this->assertEquals([1.08], $cache->get('exchange-rates'));
        $this->assertFalse($cache->child('router')->has('GET /ping'));

        $driver->clear();
    }
}
