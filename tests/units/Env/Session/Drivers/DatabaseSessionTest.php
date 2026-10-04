<?php

namespace Cube\Tests\Units\Env\Session\Drivers;

use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Env\Session\Drivers\DatabaseSession;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseSessionTest extends TestCase
{
    use TestMultipleDrivers;

    #[DataProvider('getDatabases')]
    public function test_opening_creates_the_session_table(Database $database)
    {
        $database->asGlobalInstance(function (Database $database) {
            $this->assertFalse($database->hasTable('__cube_session'));

            $handler = $this->openHandler();
            $handler->open('', 'session');

            $this->assertTrue($database->hasTable('__cube_session'));
        });
    }

    #[DataProvider('getDatabases')]
    public function test_a_written_session_is_read_back(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();

            $this->assertEquals('', $handler->read('unknown-id'));

            // NUL bytes come with every serialized protected property
            $data = serialize(['cart' => ['a-product'], "\0*\0hidden" => true]);
            $handler->write('session-id', $data);
            $this->assertEquals($data, $handler->read('session-id'));

            $handler->write('session-id', 'overwritten');
            $this->assertEquals('overwritten', $handler->read('session-id'));
        });
    }

    #[DataProvider('getDatabases')]
    public function test_validate_id_only_accepts_stored_sessions(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');

            $this->assertTrue($handler->validateId('session-id'));
            $this->assertFalse($handler->validateId('planted-id'));
        });
    }

    #[DataProvider('getDatabases')]
    public function test_destroy_removes_the_session(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');

            $handler->destroy('session-id');

            $this->assertFalse($handler->validateId('session-id'));
        });
    }

    #[DataProvider('getDatabases')]
    public function test_gc_removes_expired_sessions_only(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('expired-id', 'content');
            $handler->write('recent-id', 'content');
            Query::update('__cube_session')->set('updated_at', time() - 7200)->where('id', 'expired-id')->fetch();

            $this->assertEquals(1, $handler->gc(3600));

            $this->assertFalse($handler->validateId('expired-id'));
            $this->assertTrue($handler->validateId('recent-id'));
        });
    }

    #[DataProvider('getDatabases')]
    public function test_update_timestamp_keeps_a_session_alive(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');
            Query::update('__cube_session')->set('updated_at', time() - 7200)->where('id', 'session-id')->fetch();

            $handler->updateTimestamp('session-id', 'content');

            $this->assertEquals(0, $handler->gc(3600));
        });
    }

    protected function openHandler(): DatabaseSession
    {
        $handler = new DatabaseSession();
        $handler->open('', 'session');

        return $handler;
    }
}
