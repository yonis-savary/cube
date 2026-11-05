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
    public function testAnEventIsNamedAfterItsClass()
    {
        $this->assertEquals(ProductWasShipped::class, (new ProductWasShipped('PRD-1'))->getName());
    }

    public function testACustomEventIsNamedAfterItsFirstArgument()
    {
        $event = new CustomEvent('product-was-shipped', ['reference' => 'PRD-1']);

        $this->assertEquals('product-was-shipped', $event->getName());
        $this->assertEquals(['reference' => 'PRD-1'], $event->data);
    }

    public function testACustomEventCarriesNoDataByDefault()
    {
        $this->assertNull((new CustomEvent('product-was-shipped'))->data);
    }

    public function testDispatchReachesTheGlobalDispatcher()
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

    public function testDispatchCanTargetAnotherDispatcher()
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

    public function testOnSubscribesToTheGlobalDispatcher()
    {
        $received = null;

        Events::withInstance(new Events(), function () use (&$received) {
            ProductWasShipped::on(function (ProductWasShipped $event) use (&$received) {
                $received = $event->reference;
            });

            (new ProductWasShipped('PRD-1'))->dispatch();
        });

        $this->assertEquals('PRD-1', $received);
    }

    public function testOnCanTargetAnotherDispatcher()
    {
        $global = new Events();
        $local = new Events();

        $calls = 0;
        $countCall = function () use (&$calls) { ++$calls; };
        $global->asGlobalInstance(fn () => ProductWasShipped::on($countCall, $local));

        (new ProductWasShipped('PRD-1'))->dispatch($global);
        $this->assertEquals(0, $calls);

        (new ProductWasShipped('PRD-1'))->dispatch($local);
        $this->assertEquals(1, $calls);
    }

    public function testAnEventIsNotPreventedByDefault()
    {
        $this->assertFalse((new ProductWasShipped('PRD-1'))->isPrevented());
    }

    public function testPreventMarksTheEventAndGivesItBack()
    {
        $event = new ProductWasShipped('PRD-1');

        $this->assertSame($event, $event->prevent());
        $this->assertTrue($event->isPrevented());
    }

    public function testDispatchTellsWhetherTheEventWentThrough()
    {
        $dispatcher = new Events();

        $this->assertTrue((new ProductWasShipped('PRD-1'))->dispatch($dispatcher));

        $dispatcher->on(ProductWasShipped::class, fn (ProductWasShipped $event) => $event->prevent());
        $event = new ProductWasShipped('PRD-1');

        $this->assertFalse($event->dispatch($dispatcher));
        $this->assertTrue($event->isPrevented());
    }
}
