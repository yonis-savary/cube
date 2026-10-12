<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Cache\Clear;
use Cube\Env\Cache;
use Cube\Env\Cache\CacheConfiguration;
use Cube\Env\Cache\LocalDiskCache\LocalDiskCache;
use Cube\Env\Storage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CacheClearTest extends TestCase
{
    /** The command called a Cache::getStorage() that does not exist, so the documented way to revoke every remember-me cookie crashed. */
    public function testEveryEntryIsCleared()
    {
        $storage = Storage::getInstance()->child(uniqid('cache-clear-test-'));
        $cache = new Cache(new CacheConfiguration(new LocalDiskCache($storage)));
        $cache->set('remember-me-token', 42);

        ob_start();
        $exitCode = Cache::withInstance($cache, fn () => (new Clear())->execute(Args::fromArgv([])));
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertFalse($cache->has('remember-me-token'));
        $this->assertStringContainsString('remember-me-token', $output);

        rmdir($storage->getRoot());
    }
}
