<?php

namespace YonisSavary\Cube\Tests\Units\Events;

use PHPUnit\Framework\TestCase;
use YonisSavary\Cube\Event\CustomEvent;
use YonisSavary\Cube\Event\EventDispatcher;

class EventDispatcherTest extends TestCase
{
    public function test_dispatch()
    {
        $class = new class extends EventDispatcher {};

        $object = new $class;

        $myValue = 0;

        $object->on('my-event', function(CustomEvent $event) use (&$myValue) { $myValue = $event->data ?? 5; });

        $this->assertEquals(0, $myValue);

        $object->dispatch(new CustomEvent('my-event'));

        $this->assertEquals(5, $myValue);

        $object->dispatch(new CustomEvent('my-event', 12));

        $this->assertEquals(12, $myValue);

    }
}