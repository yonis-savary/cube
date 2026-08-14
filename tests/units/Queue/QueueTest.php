<?php

namespace Cube\Tests\Units\Queue;

use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Drivers\RedisQueue;
use Cube\Tests\Units\Queue\Classes\FailingQueue;
use Cube\Tests\Units\Queue\Classes\GreetingQueue;
use Cube\Tests\Units\Queue\Classes\NumberQueue;
use Cube\Tests\Units\Queue\Classes\SelfStoppingQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    protected const DRIVER_TEST_IDENTIFIER = 'cube-driver-test';

    public static function getQueueDrivers() {
        return [
            "redis" => [ new RedisQueue() ],
            "local-disk" => [ new LocalDiskQueueDriver() ],
        ];
    }

    #[DataProvider("getQueueDrivers")]
    public function test_loop(QueueDriver $driver) {
        $queue = new NumberQueue($driver);
        $queue->flush();

        $queue->push(1);
        $queue->push(2,3,4);
        $queue->push(5);

        $queue->processNext();
        $queue->processNext();
        $queue->processNext();

        $this->assertEquals([1,2,3,4,5], $queue->numbers);
    }

    /**
     * A driver used to wait for an item to show up, which made processNext() hang forever on
     * an empty queue and left loop() unable to pace itself.
     */
    #[DataProvider("getQueueDrivers")]
    public function test_an_empty_queue_gives_the_hand_back(QueueDriver $driver) {
        $queue = new NumberQueue($driver);
        $queue->flush();

        $start = microtime(true);
        $processed = $queue->processNext();

        $this->assertFalse($processed);
        $this->assertLessThan(5, microtime(true) - $start);
    }

    #[DataProvider("getQueueDrivers")]
    public function test_a_driver_gives_null_when_it_holds_nothing(QueueDriver $driver) {
        $driver->setIdentifier(self::DRIVER_TEST_IDENTIFIER);
        $driver->flush();

        $this->assertNull($driver->next());
    }

    #[DataProvider("getQueueDrivers")]
    public function test_a_driver_gives_the_arguments_back_untouched(QueueDriver $driver) {
        $driver->setIdentifier(self::DRIVER_TEST_IDENTIFIER);
        $driver->flush();

        $driver->push(['first', ['nested' => true], 42]);

        $this->assertEquals(['first', ['nested' => true], 42], $driver->next());
    }

    #[DataProvider("getQueueDrivers")]
    public function test_a_driver_serves_its_items_in_order(QueueDriver $driver) {
        $driver->setIdentifier(self::DRIVER_TEST_IDENTIFIER);
        $driver->flush();

        $driver->push(['first']);
        $driver->push(['second']);

        $this->assertEquals(['first'], $driver->next());
        $this->assertEquals(['second'], $driver->next());
    }

    #[DataProvider("getQueueDrivers")]
    public function test_processing_one_item_leaves_the_others_alone(QueueDriver $driver) {
        $queue = new NumberQueue($driver);
        $queue->flush();

        $queue->push(1);
        $queue->push(2);

        $this->assertTrue($queue->processNext());
        $this->assertEquals([1], $queue->numbers);
    }

    #[DataProvider("getQueueDrivers")]
    public function test_flush_empties_the_queue(QueueDriver $driver) {
        $queue = new NumberQueue($driver);

        $queue->push(1);
        $queue->push(2);
        $queue->flush();

        $this->assertFalse($queue->processNext());
        $this->assertEquals([], $queue->numbers);
    }

    #[DataProvider("getQueueDrivers")]
    public function test_a_failing_item_is_dropped_by_default(QueueDriver $driver) {
        $queue = new FailingQueue($driver);
        $queue->flush();

        $queue->push('some-value');

        $this->assertFalse($queue->processNext());
        $this->assertEquals(1, $queue->attempts);

        $this->assertFalse($queue->processNext());
        $this->assertEquals(1, $queue->attempts); // nothing left to retry
    }

    #[DataProvider("getQueueDrivers")]
    public function test_on_error_can_push_the_failed_item_back(QueueDriver $driver) {
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

    #[DataProvider("getQueueDrivers")]
    public function test_a_loop_leaves_when_the_queue_is_asked_to_stop(QueueDriver $driver) {
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
    #[DataProvider("getQueueDrivers")]
    public function test_a_loop_leaves_on_sigterm(QueueDriver $driver) {
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

    public function test_the_signal_handlers_are_given_back_after_a_loop() {
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

    public function test_the_identifier_is_stable_and_belongs_to_the_class() {
        $this->assertEquals(md5(NumberQueue::class), NumberQueue::getIdentifier());
        $this->assertNotEquals(NumberQueue::getIdentifier(), FailingQueue::getIdentifier());
    }

    public function test_the_static_entry_point_pushes_on_the_default_driver() {
        GreetingQueue::$greeted = [];

        $queue = new GreetingQueue();
        $queue->flush();

        GreetingQueue::queue('Alice');

        $this->assertTrue($queue->processNext());
        $this->assertEquals(['Alice'], GreetingQueue::$greeted);
    }
}
