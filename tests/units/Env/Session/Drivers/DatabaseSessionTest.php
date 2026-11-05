<?php

namespace Cube\Tests\Units\Env\Session\Drivers;

use Cube\Data\Database\Database;
use Cube\Data\Database\Query;
use Cube\Env\Session\Drivers\DatabaseSession;
use Cube\Env\Session\SessionConfiguration;
use Cube\Tests\Units\Database\TestMultipleDrivers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DatabaseSessionTest extends TestCase
{
    use TestMultipleDrivers;

    #[DataProvider('getDatabases')]
    public function testOpeningCreatesTheSessionTable(Database $database)
    {
        $database->asGlobalInstance(function (Database $database) {
            $this->assertFalse($database->hasTable('__cube_session'));

            $handler = $this->openHandler();
            $handler->open('', 'session');

            $this->assertTrue($database->hasTable('__cube_session'));
        });
    }

    #[DataProvider('getDatabases')]
    public function testAWrittenSessionIsReadBack(Database $database)
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
    public function testValidateIdOnlyAcceptsStoredSessions(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');

            $this->assertTrue($handler->validateId('session-id'));
            $this->assertFalse($handler->validateId('planted-id'));
        });
    }

    #[DataProvider('getDatabases')]
    public function testDestroyRemovesTheSession(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');

            $handler->destroy('session-id');

            $this->assertFalse($handler->validateId('session-id'));
        });
    }

    #[DataProvider('getDatabases')]
    public function testGcRemovesExpiredSessionsOnly(Database $database)
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
    public function testUpdateTimestampKeepsASessionAlive(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');
            Query::update('__cube_session')->set('updated_at', time() - 7200)->where('id', 'session-id')->fetch();

            $handler->updateTimestamp('session-id', 'content');

            $this->assertEquals(0, $handler->gc(3600));
        });
    }

    /** Expiry relied on the garbage collector alone, which packaged PHP never runs : a stolen id stayed valid forever. */
    #[DataProvider('getDatabases')]
    public function testAnExpiredSessionIsRefusedWithoutTheGarbageCollector(Database $database)
    {
        $database->asGlobalInstance(function () {
            $handler = $this->openHandler();
            $handler->write('session-id', 'content');
            Query::update('__cube_session')->set('updated_at', time() - (int) ini_get('session.gc_maxlifetime') - 1)->where('id', 'session-id')->fetch();

            $this->assertFalse($handler->validateId('session-id'));
            $this->assertEquals('', $handler->read('session-id'));

            $handler->write('session-id', 'new content');
            $this->assertEquals('new content', $handler->read('session-id'));
        });
    }

    /** A request still running with the old id wrote it back after a logout destroyed it. */
    #[DataProvider('getDatabases')]
    public function testASessionDestroyedMeanwhileIsNotWrittenBack(Database $database)
    {
        $database->asGlobalInstance(function () {
            $this->openHandler()->write('session-id', 'logged in');

            $slowRequest = $this->openHandler();
            $slowRequest->read('session-id');

            $this->openHandler()->destroy('session-id');
            $slowRequest->write('session-id', 'logged in');

            $this->assertFalse($this->openHandler()->validateId('session-id'));
        });
    }

    /** The constructor resolved the global Database, whose PDO made `configuration:cache` fail on the SessionConfiguration. */
    #[DataProvider('getDatabases')]
    public function testASessionConfigurationCanBeCached(Database $database)
    {
        $database->asGlobalInstance(function (Database $database) {
            $configuration = unserialize(serialize(new SessionConfiguration(driver: new DatabaseSession())));

            $this->assertSame($database, $configuration->driver->getDatabase());
        });
    }

    protected function openHandler(): DatabaseSession
    {
        $handler = new DatabaseSession();
        $handler->open('', 'session');

        return $handler;
    }
}
