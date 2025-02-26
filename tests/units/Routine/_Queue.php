<?php

namespace Cube\Tests\Units\Routine;

use Cube\Tests\App\Queues\Calculator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class QueueTest extends TestCase
{
    public function testCreation()
    {
        Calculator::flush(true);
        $this->assertEquals(0, Calculator::countToProcess());

        Calculator::addAddition(5, 5);
        Calculator::addAddition(15, 5);
        Calculator::addAddition(-5, -2);

        $this->assertEquals(3, Calculator::countToProcess());

        Calculator::processNext();
        $this->assertEquals(2, Calculator::countToProcess());

        Calculator::processNext();
        $this->assertEquals(1, Calculator::countToProcess());

        Calculator::processNext();
        $this->assertEquals(0, Calculator::countToProcess());

        Calculator::processNext();
        $this->assertEquals(0, Calculator::countToProcess());
    }
}
