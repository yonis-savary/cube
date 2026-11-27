<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Env\Storage;
use Cube\Tests\Units\Database\Classes\LosesItsConnection;
use Cube\Tests\Units\Env\Classes\SpyLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DatabaseConnectionTest extends TestCase
{
    use LosesItsConnection;

    #[DataProvider('getServerProviders')]
    public function testAPingAnswersWhetherTheConnectionStillWorks(DatabaseProvider $provider)
    {
        $database = $this->reconnectableDatabase($provider);
        $this->assertTrue($database->ping());

        $this->closeFromTheServer($database, $provider);

        $this->assertFalse($database->ping());
    }

    #[DataProvider('getServerProviders')]
    public function testKeepingAliveReconnectsAClosedConnection(DatabaseProvider $provider)
    {
        $database = $this->reconnectableDatabase($provider);
        $this->closeFromTheServer($database, $provider);
        $logger = new SpyLogger();

        $database->keepAlive($logger);

        $this->assertEquals([['one' => 1]], $database->query('SELECT 1 AS one'));
        $this->assertEquals(['warning'], $logger->levels());
    }

    #[DataProvider('getServerProviders')]
    public function testKeepingAliveLeavesAWorkingConnectionAlone(DatabaseProvider $provider)
    {
        $database = $this->reconnectableDatabase($provider);
        $connection = $database->getConnection();

        $database->keepAlive(new SpyLogger());

        $this->assertSame($connection, $database->getConnection());
    }

    public function testADatabaseBuiltFromAConnectionCannotReconnect()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('existing PDO connection');

        Database::fromPDO(new \PDO('sqlite::memory:'))->reconnect();
    }

    public function testAnInMemoryDatabaseCannotReconnect()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('in-memory');

        (new Database())->reconnect();
    }

    public function testAnSqliteFileKeepsItsContentWhenReconnecting()
    {
        $file = 'reconnect-'.uniqid().'.sqlite';
        $database = new Database('sqlite', $file);
        $database->exec('CREATE TABLE note (body TEXT)');
        $database->exec("INSERT INTO note VALUES ('kept')");

        $database->reconnect();

        $this->assertEquals([['body' => 'kept']], $database->query('SELECT body FROM note'));
        unlink(Storage::getInstance()->path($file));
    }
}
