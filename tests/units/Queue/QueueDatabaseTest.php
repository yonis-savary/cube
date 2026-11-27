<?php

namespace Cube\Tests\Units\Queue;

use Cube\Data\Database\DatabaseConfiguration;
use Cube\Env\Configuration;
use Cube\Tests\Units\Database\Classes\LosesItsConnection;
use Cube\Tests\Units\Database\DatabaseProvider;
use Cube\Tests\Units\Queue\Classes\DatabaseWorkerQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueueDatabaseTest extends TestCase
{
    use LosesItsConnection;

    /** A worker idle longer than the server's wait_timeout failed every job after that. */
    #[DataProvider('getServerProviders')]
    public function testALoopReconnectsALostDatabaseBeforeTheNextItem(DatabaseProvider $provider)
    {
        $database = $this->reconnectableDatabase($provider);
        $queue = new DatabaseWorkerQueue(fn () => $this->closeFromTheServer($database, $provider));
        $queue->flush();
        $queue->push('lose-the-connection');
        $queue->push('query');

        (new Configuration(new DatabaseConfiguration(keepAliveInterval: 1)))->asGlobalInstance(
            fn () => $database->asGlobalInstance(fn () => $queue->loop())
        );

        $this->assertEquals([1], $queue->answers);
        $queue->flush();
    }
}
