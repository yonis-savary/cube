<?php 

namespace Cube\Tests\Units\Queue;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Drivers\LocalDiskQueueDriver;
use Cube\Queue\Drivers\QueueDriver;
use Cube\Queue\Drivers\RedisQueue;
use Cube\Queue\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public static function getQueueDrivers() {
        return [
            "redis" => [ new RedisQueue() ],
            "local-disk" => [ new LocalDiskQueueDriver() ],
        ];
    }

    #[DataProvider("getQueueDrivers")]
    public function test_loop(QueueDriver $driver) {
        $dummyQueue = new class($driver) extends Queue {
            public $numbers = [];
            protected QueueDriver $outerDriver;

            protected function getDriver(): QueueDriver        { return $this->outerDriver; }
            public function getLogger(): Logger              { return new NullLogger(); }
            public function __invoke(int ...$numbers)        { array_push($this->numbers, ...$numbers); }
            public function __construct(QueueDriver $driver) { $this->outerDriver = $driver; }
        };

        $dummyQueue->push(1);
        $dummyQueue->push(2,3,4);
        $dummyQueue->push(5);

        $dummyQueue->processNext();
        $dummyQueue->processNext();
        $dummyQueue->processNext();

        $this->assertEquals([1,2,3,4,5], $dummyQueue->numbers);
    }
}