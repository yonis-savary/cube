<?php

namespace Cube\Tests\Units\Env\Session\Drivers;

use Cube\Data\Database\Database;
use Cube\Env\Session\Drivers\DatabaseSession;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class DatabaseSessionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        session_write_close();
        Database::setInstance((new SQLiteProvider())->getEmptyDatabase());
    }

    public function test_the_session_is_stored_in_the_database()
    {
        $driver = $this->newDriver('shop');
        $driver->set('cart', ['a-product']);
        $sessionId = session_id();

        $this->reopenSession($sessionId);

        $this->assertTrue($driver->validateId($sessionId));
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
        $this->assertFalse($driver->validateId($oldSessionId));
        $this->assertEquals(['a-product'], $_SESSION['cube']['shop']['cart']);
    }

    protected function newDriver(string $namespace): DatabaseSession
    {
        $driver = new DatabaseSession();
        $driver->initialize($namespace);

        return $driver;
    }

    protected function reopenSession(string $sessionId): void
    {
        session_write_close();
        $_SESSION = [];

        session_id($sessionId);
        session_start();
    }
}
