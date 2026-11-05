<?php

namespace Cube\Tests\Units\Queue;

use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Drivers\RedisQueue;
use Cube\Tests\Units\Queue\Classes\FailingQueue;
use Cube\Tests\Units\Queue\Classes\GreetingQueue;
use Cube\Tests\Units\Queue\Classes\LostRedisQueue;
use Cube\Tests\Units\Queue\Classes\NumberQueue;
use Cube\Tests\Units\Queue\Classes\SelfStoppingQueue;
use Cube\Utils\SignedPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Cube\env;

/**
 * @internal
 */
class QueueTest extends TestCase
{
    protected const DRIVER_TEST_IDENTIFIER = 'cube-driver-test';

    #[DataProvider('getQueueDrivers')]
    public function testLoop(QueueDriver $driver)
    {
        $queue = new NumberQueue($driver);
        $queue->flush();

        $queue->push(1);
        $queue->push(2, 3, 4);
        $queue->push(5);

        $queue->processNext();
        $queue->processNext();
        $queue->processNext();

        $this->assertEquals([1, 2, 3, 4, 5], $queue->numbers);
    }

    /**
     * A driver used to wait for an item to show up, which made processNext() hang forever on
     * an empty queue and left loop() unable to pace itself.
     */
    #[DataProvider('getQueueDrivers')]
    public function testAnEmptyQueueGivesTheHandBack(QueueDriver $driver)
    {
        $queue = new NumberQueue($driver);
        $queue->flush();

        $start = microtime(true);
        $processed = $queue->processNext();

        $this->assertFalse($processed);
        $this->assertLessThan(5, microtime(true) - $start);
    }

    #[DataProvider('getQueueDrivers')]
    public function testADriverGivesNullWhenItHoldsNothing(QueueDriver $driver)
    {
        $driver->setIdentifier(self::DRIVER_TEST_IDENTIFIER);
        $driver->flush();

        $this->assertNull($driver->next());
    }

    #[DataProvider('getQueueDrivers')]
    public function testADriverGivesTheArgumentsBackUntouched(QueueDriver $driver)
    {
        $driver->setIdentifier(self::DRIVER_TEST_IDENTIFIER);
        $driver->flush();

        $driver->push(['first', ['nested' => true], 42]);

        $this->assertEquals(['first', ['nested' => true], 42], $driver->next());
    }

    #[DataProvider('getQueueDrivers')]
    public function testADriverServesItsItemsInOrder(QueueDriver $driver)
    {
        $driver->setIdentifier(self::DRIVER_TEST_IDENTIFIER);
        $driver->flush();

        $driver->push(['first']);
        $driver->push(['second']);

        $this->assertEquals(['first'], $driver->next());
        $this->assertEquals(['second'], $driver->next());
    }

    #[DataProvider('getQueueDrivers')]
    public function testProcessingOneItemLeavesTheOthersAlone(QueueDriver $driver)
    {
        $queue = new NumberQueue($driver);
        $queue->flush();

        $queue->push(1);
        $queue->push(2);

        $this->assertTrue($queue->processNext());
        $this->assertEquals([1], $queue->numbers);
    }

    #[DataProvider('getQueueDrivers')]
    public function testFlushEmptiesTheQueue(QueueDriver $driver)
    {
        $queue = new NumberQueue($driver);

        $queue->push(1);
        $queue->push(2);
        $queue->flush();

        $this->assertFalse($queue->processNext());
        $this->assertEquals([], $queue->numbers);
    }

    #[DataProvider('getQueueDrivers')]
    public function testAFailingItemIsDroppedByDefault(QueueDriver $driver)
    {
        $queue = new FailingQueue($driver);
        $queue->flush();

        $queue->push('some-value');

        $this->assertFalse($queue->processNext());
        $this->assertEquals(1, $queue->attempts);

        $this->assertFalse($queue->processNext());
        $this->assertEquals(1, $queue->attempts); // nothing left to retry
    }

    #[DataProvider('getQueueDrivers')]
    public function testOnErrorCanPushTheFailedItemBack(QueueDriver $driver)
    {
        $queue = new FailingQueue($driver);
        $queue->flush();
        $queue->repushOnError = true;

        $queue->push('some-value');

        $queue->processNext();
        $queue->processNext();

        $this->assertEquals(2, $queue->attempts);

        $queue->repushOnError = false;
        $queue->processNext();
        $queue->flush();
    }

    #[DataProvider('getQueueDrivers')]
    public function testALoopLeavesWhenTheQueueIsAskedToStop(QueueDriver $driver)
    {
        $queue = new SelfStoppingQueue($driver);
        $queue->flush();

        $queue->push('first');
        $queue->push('second');

        $queue->loop();

        // Stopped between two items, never halfway through one
        $this->assertEquals(['first'], $queue->seen);

        $queue->flush();
    }

    /**
     * The signal a process manager sends is what has to end a worker : without a handler the
     * default action kills php on the spot, dropping the item being processed.
     */
    #[DataProvider('getQueueDrivers')]
    public function testALoopLeavesOnSigterm(QueueDriver $driver)
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl and posix are needed to signal a worker');
        }

        $queue = new SelfStoppingQueue($driver);
        $queue->flush();
        $queue->stopWithSignal = true;

        $queue->push('first');
        $queue->push('second');

        $queue->loop();

        $this->assertEquals(['first'], $queue->seen);

        $queue->flush();
    }

    public static function getQueueDrivers()
    {
        return [
            'redis' => [new RedisQueue()],
            'local-disk' => [new LocalDiskQueueDriver()],
        ];
    }

    public function testTheSignalHandlersAreGivenBackAfterALoop()
    {
        if (!function_exists('pcntl_signal_get_handler')) {
            $this->markTestSkipped('pcntl is needed to read the installed handlers');
        }

        $queue = new SelfStoppingQueue(new LocalDiskQueueDriver());
        $queue->flush();
        $queue->push('first');

        $queue->loop();

        // A handler left behind would turn the next SIGTERM into a flag nobody reads
        $this->assertEquals(SIG_DFL, pcntl_signal_get_handler(SIGTERM));
        $this->assertEquals(SIG_DFL, pcntl_signal_get_handler(SIGINT));
    }

    public function testTheIdentifierIsStableAndBelongsToTheClass()
    {
        $this->assertEquals(md5(NumberQueue::class), NumberQueue::getIdentifier());
        $this->assertNotEquals(NumberQueue::getIdentifier(), FailingQueue::getIdentifier());
    }

    public function testTheStaticEntryPointPushesOnTheDefaultDriver()
    {
        GreetingQueue::$greeted = [];

        $queue = new GreetingQueue();
        $queue->flush();

        GreetingQueue::queue('Alice');

        $this->assertTrue($queue->processNext());
        $this->assertEquals(['Alice'], GreetingQueue::$greeted);
    }

    /** Jobs are unserialized : a Redis anyone can write to used to be the only option. */
    public function testARedisQueueAuthenticatesWithItsPassword()
    {
        $this->expectExceptionMessage('refused the password');

        new RedisQueue('127.0.0.1', 6379, 'a-wrong-password');
    }

    /** Jobs are unserialized : one written to Redis by anyone but the application used to run as is. */
    public function testARedisQueueDropsAJobItDidNotSign()
    {
        $queue = new NumberQueue(new RedisQueue());
        $queue->flush();

        $redis = new \Redis();
        $redis->connect(env('QUEUE_REDIS_HOST'), 6379);
        $redis->rPush(NumberQueue::getIdentifier(), serialize([42]));

        $this->assertFalse($queue->processNext());
        $this->assertEquals([], $queue->numbers);
    }

    /** A payload that is not a list of arguments hit the `?array` return type and killed the worker loop. */
    public function testARedisQueueSkipsAMalformedJob()
    {
        $queue = new NumberQueue(new RedisQueue());
        $queue->flush();

        $redis = new \Redis();
        $redis->connect(env('QUEUE_REDIS_HOST'), 6379);
        $redis->rPush(NumberQueue::getIdentifier(), SignedPayload::sign(serialize('not a list of arguments')));

        $this->assertFalse($queue->processNext());
    }

    /** A failed reconnection threw outside of processNext()'s try/catch and killed the worker loop. */
    public function testARedisQueueSurvivesAFailedReconnection()
    {
        $driver = new LostRedisQueue();
        $queue = new NumberQueue($driver);
        $driver->loseTheServer();

        $this->assertFalse($queue->processNext());
    }
}
