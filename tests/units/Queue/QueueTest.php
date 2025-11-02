<?php 

namespace Cube\Tests\Units\Queue;

use Cube\Env\Logger\Logger;
use Cube\Env\Logger\NullLogger;
use Cube\Queue\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    protected static array $numbers = [];

    public static function addNumber(int $number) {
        self::$numbers[] = $number;
    }

    public function test_loop() {
        $dummyQueue = new class extends Queue {
            public function getLogger(): Logger {
                return new NullLogger();
            }
        };
        $dummyQueue->flush();

        $instance = $dummyQueue::getInstance();

        $instance->push([self::class, "addNumber"], 1);
        $instance->push([self::class, "addNumber"], 2);
        $instance->push([self::class, "addNumber"], 5);

        $instance->processNext();
        $instance->processNext();
        $instance->processNext();

        $this->assertEquals([1,2,5], self::$numbers);
    }
}