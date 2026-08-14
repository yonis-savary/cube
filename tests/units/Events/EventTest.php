<?php

namespace Cube\Tests\Units\Events;

use Cube\Event\CustomEvent;
use Cube\Event\Events;
use Cube\Tests\Units\Events\Classes\ProductWasShipped;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EventTest extends TestCase
{
    public function test_an_event_is_named_after_its_class()
    {
        $this->assertEquals(ProductWasShipped::class, (new ProductWasShipped('PRD-1'))->getName());
    }

    public function test_a_custom_event_is_named_after_its_first_argument()
    {
        $event = new CustomEvent('product-was-shipped', ['reference' => 'PRD-1']);

        $this->assertEquals('product-was-shipped', $event->getName());
        $this->assertEquals(['reference' => 'PRD-1'], $event->data);
    }

    public function test_a_custom_event_carries_no_data_by_default()
    {
        $this->assertNull((new CustomEvent('product-was-shipped'))->data);
    }

    public function test_dispatch_reaches_the_global_dispatcher()
    {
        $received = null;

        Events::withInstance(new Events(), function (Events $events) use (&$received) {
            $events->on(ProductWasShipped::class, function (ProductWasShipped $event) use (&$received) {
                $received = $event->reference;
            });

            (new ProductWasShipped('PRD-1'))->dispatch();
        });

        $this->assertEquals('PRD-1', $received);
    }

    public function test_dispatch_can_target_another_dispatcher()
    {
        $global = new Events();
        $local = new Events();

        $globalCalls = 0;
        $localCalls = 0;

        $global->on(ProductWasShipped::class, function () use (&$globalCalls) { ++$globalCalls; });
        $local->on(ProductWasShipped::class, function () use (&$localCalls) { ++$localCalls; });

        $global->asGlobalInstance(fn () => (new ProductWasShipped('PRD-1'))->dispatch($local));

        $this->assertEquals(0, $globalCalls);
        $this->assertEquals(1, $localCalls);
    }

    public function test_dispatch_gives_the_event_back()
    {
        $event = new ProductWasShipped('PRD-1');

        $this->assertSame($event, $event->dispatch(new Events()));
    }
}
