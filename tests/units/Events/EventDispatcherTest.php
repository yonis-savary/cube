<?php

namespace Cube\Tests\Units\Events;

use Cube\Event\CustomEvent;
use Cube\Event\EventDispatcher;
use Cube\Event\Events;
use Cube\Event\Events\PreventedEvent;
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

    public function testAStringIsDispatchedAsACustomEvent()
    {
        $dispatcher = new Events();
        $received = null;

        $dispatcher->on('product-was-shipped', function ($event) use (&$received) { $received = $event; });
        $dispatcher->dispatch('product-was-shipped');

        $this->assertInstanceOf(CustomEvent::class, $received);
        $this->assertEquals('product-was-shipped', $received->getName());
        $this->assertNull($received->data);
    }

    public function testAnEventObjectReachesTheListenersOfItsClass()
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

    public function testEveryListenerRunsInRegistrationOrder()
    {
        $dispatcher = new Events();
        $calls = [];

        $dispatcher->on('product-was-shipped', function () use (&$calls) { $calls[] = 'first'; });
        $dispatcher->on('product-was-shipped', function () use (&$calls) { $calls[] = 'second'; });
        $dispatcher->dispatch('product-was-shipped');

        $this->assertEquals(['first', 'second'], $calls);
    }

    public function testTheSameCallbackCanBeRegisteredTwice()
    {
        $dispatcher = new Events();
        $calls = 0;
        $listener = function () use (&$calls) { ++$calls; };

        $dispatcher->on('product-was-shipped', $listener);
        $dispatcher->on('product-was-shipped', $listener);
        $dispatcher->dispatch('product-was-shipped');

        $this->assertEquals(2, $calls);
    }

    public function testAListenerOnlyHearsTheEventItSubscribedTo()
    {
        $dispatcher = new Events();
        $calls = 0;

        $dispatcher->on('product-was-shipped', function () use (&$calls) { ++$calls; });
        $dispatcher->dispatch('product-was-delivered');

        $this->assertEquals(0, $calls);
    }

    public function testAnEventWithoutAnyListenerGoesThrough()
    {
        $dispatcher = new Events();

        $dispatcher->dispatch('nobody-listens-to-me');

        $this->expectNotToPerformAssertions();
    }

    /**
     * Subscriptions are keyed by the exact name an event answers, so a child event does not
     * reach the listeners of its parent : subscribe to each class you want to hear about.
     */
    public function testAChildEventDoesNotReachTheListenersOfItsParent()
    {
        $dispatcher = new Events();
        $calls = 0;

        $dispatcher->on(ProductWasShipped::class, function () use (&$calls) { ++$calls; });
        $dispatcher->dispatch(new ProductWasShippedAbroad('PRD-1'));

        $this->assertEquals(0, $calls);
    }

    public function testSubscribingGivesTheDispatcherBack()
    {
        $dispatcher = new Events();

        $this->assertSame($dispatcher, $dispatcher->on('product-was-shipped', fn () => null));
    }

    public function testTwoDispatchersHoldTheirOwnSubscriptions()
    {
        $first = new Events();
        $second = new Events();
        $calls = 0;

        $first->on('product-was-shipped', function () use (&$calls) { ++$calls; });
        $second->dispatch('product-was-shipped');

        $this->assertEquals(0, $calls);
    }

    public function testPreventingAnEventSkipsTheRemainingListeners()
    {
        $dispatcher = new Events();
        $calls = [];

        $dispatcher->on('product-was-shipped', function () use (&$calls) { $calls[] = 'first'; });
        $dispatcher->on('product-was-shipped', function (CustomEvent $event) use (&$calls) {
            $calls[] = 'second';
            $event->prevent();
        });
        $dispatcher->on('product-was-shipped', function () use (&$calls) { $calls[] = 'third'; });
        $dispatcher->dispatch('product-was-shipped');

        $this->assertEquals(['first', 'second'], $calls);
    }

    public function testAPreventedEventDispatchesAPreventedEventWrappingIt()
    {
        $dispatcher = new Events();
        $received = null;

        $dispatcher->on(ProductWasShipped::class, fn (ProductWasShipped $event) => $event->prevent());
        $dispatcher->on(PreventedEvent::class, function (PreventedEvent $event) use (&$received) {
            $received = $event;
        });

        $shipped = new ProductWasShipped('PRD-1');
        $dispatcher->dispatch($shipped);

        $this->assertInstanceOf(PreventedEvent::class, $received);
        $this->assertSame($shipped, $received->event);
    }

    public function testAPreventedCustomEventIsWrappedToo()
    {
        $dispatcher = new Events();
        $received = null;

        $dispatcher->on('product-was-shipped', fn (CustomEvent $event) => $event->prevent());
        $dispatcher->on(PreventedEvent::class, function (PreventedEvent $event) use (&$received) {
            $received = $event->event->getName();
        });
        $dispatcher->dispatch('product-was-shipped');

        $this->assertEquals('product-was-shipped', $received);
    }

    public function testAnEventGoingThroughEveryListenerDispatchesNothingMore()
    {
        $dispatcher = new Events();
        $calls = 0;

        $dispatcher->on(ProductWasShipped::class, fn () => null);
        $dispatcher->on(PreventedEvent::class, function () use (&$calls) { ++$calls; });
        $dispatcher->dispatch(new ProductWasShipped('PRD-1'));

        $this->assertEquals(0, $calls);
    }

    public function testThePreventedEventStaysOnTheDispatcherThatPreventedIt()
    {
        $global = new Events();
        $local = new Events();
        $globalCalls = 0;
        $localCalls = 0;

        $local->on(ProductWasShipped::class, fn (ProductWasShipped $event) => $event->prevent());
        $global->on(PreventedEvent::class, function () use (&$globalCalls) { ++$globalCalls; });
        $local->on(PreventedEvent::class, function () use (&$localCalls) { ++$localCalls; });

        $global->asGlobalInstance(fn () => (new ProductWasShipped('PRD-1'))->dispatch($local));

        $this->assertEquals(0, $globalCalls);
        $this->assertEquals(1, $localCalls);
    }

    public function testPreventingAPreventedEventDoesNotLoop()
    {
        $dispatcher = new Events();
        $calls = 0;

        $dispatcher->on(ProductWasShipped::class, fn (ProductWasShipped $event) => $event->prevent());
        $dispatcher->on(PreventedEvent::class, function (PreventedEvent $event) use (&$calls) {
            ++$calls;
            $event->prevent();
        });
        $dispatcher->dispatch(new ProductWasShipped('PRD-1'));

        $this->assertEquals(1, $calls);
    }

    public function testDispatchSucceedsWhenNobodyListens()
    {
        $this->assertTrue((new Events())->dispatch('nobody-listens-to-me'));
    }

    public function testDispatchSucceedsWhenEveryListenerRan()
    {
        $dispatcher = new Events();
        $dispatcher->on('product-was-shipped', fn () => null);

        $this->assertTrue($dispatcher->dispatch('product-was-shipped'));
    }

    public function testDispatchFailsWhenAListenerPreventsTheEvent()
    {
        $dispatcher = new Events();
        $dispatcher->on('product-was-shipped', fn (CustomEvent $event) => $event->prevent());

        $this->assertFalse($dispatcher->dispatch('product-was-shipped'));
    }

    public function testAPreventedEventFailsEvenIfItsPreventedEventIsPreventedToo()
    {
        $dispatcher = new Events();
        $dispatcher->on('product-was-shipped', fn (CustomEvent $event) => $event->prevent());
        $dispatcher->on(PreventedEvent::class, fn (PreventedEvent $event) => $event->prevent());

        $this->assertFalse($dispatcher->dispatch('product-was-shipped'));
    }
}
