<?php

namespace Cube\Tests\Units\Env\Session\Drivers;

use Cube\Env\Session\Drivers\RedisSession;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * The save handler can only be chosen while no session is active : each test runs in its own
 * process and closes the session the bootstrap opened with the default handler.
 */
#[RunTestsInSeparateProcesses]
class RedisSessionTest extends TestCase
{
    protected function setUp(): void
    {
        session_write_close();
    }

    public function test_the_session_is_written_to_redis()
    {
        $driver = $this->newDriver();
        $driver->set('cart', ['a-product']);
        $sessionId = session_id();

        session_write_close();

        $this->assertEquals(1, $this->redis()->exists("PHPREDIS_SESSION:{$sessionId}"));
    }

    public function test_the_session_is_read_back_from_redis()
    {
        $this->newDriver('shop')->set('cart', ['a-product']);
        $this->reopenSession(session_id());

        $this->assertEquals(['a-product'], $_SESSION['cube']['shop']['cart']);
    }

    public function test_a_regenerated_id_keeps_the_values()
    {
        $driver = $this->newDriver('shop');
        $driver->set('cart', ['a-product']);
        $oldSessionId = session_id();

        $driver->regenerate();
        $newSessionId = session_id();
        $this->reopenSession($newSessionId);

        $this->assertNotEquals($oldSessionId, $newSessionId);
        $this->assertEquals(0, $this->redis()->exists("PHPREDIS_SESSION:{$oldSessionId}"));
        $this->assertEquals(['a-product'], $_SESSION['cube']['shop']['cart']);
    }

    protected function newDriver(?string $namespace = null): RedisSession
    {
        $driver = new RedisSession('127.0.0.1');
        $driver->initialize($namespace ?? uniqid('shop-'));

        return $driver;
    }

    protected function reopenSession(string $sessionId): void
    {
        session_write_close();
        $_SESSION = [];

        session_id($sessionId);
        session_start();
    }

    protected function redis(): Redis
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);

        return $redis;
    }
}
