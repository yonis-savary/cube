<?php

namespace Cube\Tests\Units\Events;

use Cube\Event\CustomEvent;
use Cube\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EventDispatcherTest extends TestCase
{
    public function testDispatch()
    {
        $class = new class extends EventDispatcher {};

        $object = new $class();

        $myValue = 0;

        $object->on('my-event', function (CustomEvent $event) use (&$myValue) { $myValue = $event->data ?? 5; });

        $this->assertEquals(0, $myValue);

        $object->dispatch(new CustomEvent('my-event'));

        $this->assertEquals(5, $myValue);

        $object->dispatch(new CustomEvent('my-event', 12));

        $this->assertEquals(12, $myValue);
    }
}
