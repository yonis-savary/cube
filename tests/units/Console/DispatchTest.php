<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Dispatch;
use Cube\Core\Injector;
use Cube\Event\Event;
use Cube\Event\Events;
use Cube\Event\Events\PostDeployment;
use Cube\Tests\Units\Console\Classes\Billing\InvoiceWasSent as BillingInvoiceWasSent;
use Cube\Tests\Units\Console\Classes\CatalogWasImported;
use Cube\Tests\Units\Console\Classes\SayHello;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DispatchTest extends TestCase
{
    protected Events $events;

    /** @var Event[] */
    protected array $received = [];

    protected function setUp(): void
    {
        $this->events = new Events();
        $this->received = [];

        $this->events->on(CatalogWasImported::class, function (Event $event) { $this->received[] = $event; });
        $this->events->on(PostDeployment::class, function (Event $event) { $this->received[] = $event; });
    }

    protected function dispatch(string ...$argv): int
    {
        return (new Dispatch($this->events, Injector::getInstance()))->execute(Args::fromArgv($argv));
    }

    public function testTheIdentifierIsCubeDispatch()
    {
        $this->assertEquals('cube:dispatch', (new Dispatch(new Events(), Injector::getInstance()))->getFullIdentifier());
    }

    public function testAnEventIsDispatchedFromItsFullClassName()
    {
        $this->assertEquals(0, $this->dispatch('--event', CatalogWasImported::class));

        $this->assertCount(1, $this->received);
        $this->assertInstanceOf(CatalogWasImported::class, $this->received[0]);
    }

    public function testTheShortOptionWorksLikeTheLongOne()
    {
        $this->assertEquals(0, $this->dispatch('-e', CatalogWasImported::class));

        $this->assertCount(1, $this->received);
    }

    public function testAnEventIsDispatchedFromItsShortClassName()
    {
        $this->assertEquals(0, $this->dispatch('--event', 'CatalogWasImported'));

        $this->assertCount(1, $this->received);
        $this->assertInstanceOf(CatalogWasImported::class, $this->received[0]);
    }

    public function testTheFrameworkPostDeploymentEventCanBeDispatched()
    {
        $this->assertEquals(0, $this->dispatch('--event', 'PostDeployment'));

        $this->assertCount(1, $this->received);
        $this->assertInstanceOf(PostDeployment::class, $this->received[0]);
    }

    public function testAMissingEventOptionAbortsWithStatus1()
    {
        $this->expectOutputRegex('/-e\|--event/');

        $this->assertEquals(1, $this->dispatch());
        $this->assertEmpty($this->received);
    }

    public function testAnUnknownEventNameAbortsWithStatus2()
    {
        $this->expectOutputRegex('/\[ProductWasLost\]/');

        $this->assertEquals(2, $this->dispatch('--event', 'ProductWasLost'));
        $this->assertEmpty($this->received);
    }

    /** The first class whose name merely ended with the given one was dispatched, whichever it was. */
    public function testAnAmbiguousEventNameAbortsWithStatus2()
    {
        $this->expectOutputRegex('/several Event classes/');

        $this->assertEquals(2, $this->dispatch('--event', 'InvoiceWasSent'));
    }

    public function testANamespaceSuffixChoosesBetweenEventsOfTheSameName()
    {
        $this->events->on(BillingInvoiceWasSent::class, function (Event $event) { $this->received[] = $event; });

        $this->assertEquals(0, $this->dispatch('--event', 'Billing\InvoiceWasSent'));
        $this->assertInstanceOf(BillingInvoiceWasSent::class, $this->received[0]);
    }

    public function testAClassThatIsNotAnEventAbortsWithStatus2()
    {
        $this->expectOutputRegex('/does not extend from Event/');

        $this->assertEquals(2, $this->dispatch('--event', SayHello::class));
        $this->assertEmpty($this->received);
    }

    public function testCallDispatchesThroughTheGlobalEventsInstance()
    {
        $this->events->asGlobalInstance(function () {
            $this->assertEquals(0, Dispatch::call(Args::fromArgv(['--event', CatalogWasImported::class])));
        });

        $this->assertCount(1, $this->received);
    }
}
