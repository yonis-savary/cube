<?php

namespace Cube\Tests\Units\Events;

use Cube\Event\CustomEvent;
use Cube\Event\EventDispatcher;
use Cube\Event\Events;
use Cube\Tests\Units\Events\Classes\ProductWasShipped;
use Cube\Tests\Units\Events\Classes\ProductWasShippedAbroad;
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

    public function test_a_string_is_dispatched_as_a_custom_event()
    {
        $dispatcher = new Events();
        $received = null;

        $dispatcher->on('product-was-shipped', function ($event) use (&$received) { $received = $event; });
        $dispatcher->dispatch('product-was-shipped');

        $this->assertInstanceOf(CustomEvent::class, $received);
        $this->assertEquals('product-was-shipped', $received->getName());
        $this->assertNull($received->data);
    }

    public function test_an_event_object_reaches_the_listeners_of_its_class()
    {
        $dispatcher = new Events();
        $received = null;

        $dispatcher->on(ProductWasShipped::class, function (ProductWasShipped $event) use (&$received) {
            $received = $event;
        });

        $event = new ProductWasShipped('PRD-1');
        $dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    public function test_every_listener_runs_in_registration_order()
    {
        $dispatcher = new Events();
        $calls = [];

        $dispatcher->on('product-was-shipped', function () use (&$calls) { $calls[] = 'first'; });
        $dispatcher->on('product-was-shipped', function () use (&$calls) { $calls[] = 'second'; });
        $dispatcher->dispatch('product-was-shipped');

        $this->assertEquals(['first', 'second'], $calls);
    }

    public function test_the_same_callback_can_be_registered_twice()
    {
        $dispatcher = new Events();
        $calls = 0;
        $listener = function () use (&$calls) { ++$calls; };

        $dispatcher->on('product-was-shipped', $listener);
        $dispatcher->on('product-was-shipped', $listener);
        $dispatcher->dispatch('product-was-shipped');

        $this->assertEquals(2, $calls);
    }

    public function test_a_listener_only_hears_the_event_it_subscribed_to()
    {
        $dispatcher = new Events();
        $calls = 0;

        $dispatcher->on('product-was-shipped', function () use (&$calls) { ++$calls; });
        $dispatcher->dispatch('product-was-delivered');

        $this->assertEquals(0, $calls);
    }

    public function test_an_event_without_any_listener_goes_through()
    {
        $dispatcher = new Events();

        $dispatcher->dispatch('nobody-listens-to-me');

        $this->expectNotToPerformAssertions();
    }

    /**
     * Subscriptions are keyed by the exact name an event answers, so a child event does not
     * reach the listeners of its parent : subscribe to each class you want to hear about.
     */
    public function test_a_child_event_does_not_reach_the_listeners_of_its_parent()
    {
        $dispatcher = new Events();
        $calls = 0;

        $dispatcher->on(ProductWasShipped::class, function () use (&$calls) { ++$calls; });
        $dispatcher->dispatch(new ProductWasShippedAbroad('PRD-1'));

        $this->assertEquals(0, $calls);
    }

    public function test_subscribing_gives_the_dispatcher_back()
    {
        $dispatcher = new Events();

        $this->assertSame($dispatcher, $dispatcher->on('product-was-shipped', fn () => null));
    }

    public function test_two_dispatchers_hold_their_own_subscriptions()
    {
        $first = new Events();
        $second = new Events();
        $calls = 0;

        $first->on('product-was-shipped', function () use (&$calls) { ++$calls; });
        $second->dispatch('product-was-shipped');

        $this->assertEquals(0, $calls);
    }
}
