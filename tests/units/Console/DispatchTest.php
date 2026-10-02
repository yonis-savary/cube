<?php

namespace Cube\Tests\Units\Console;

use Cube\Console\Args;
use Cube\Console\Commands\Dispatch;
use Cube\Core\Injector;
use Cube\Event\Event;
use Cube\Event\Events;
use Cube\Event\Events\PostDeployment;
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

    public function test_the_identifier_is_cube_dispatch()
    {
        $this->assertEquals('cube:dispatch', (new Dispatch(new Events(), Injector::getInstance()))->getFullIdentifier());
    }

    public function test_an_event_is_dispatched_from_its_full_class_name()
    {
        $this->assertEquals(0, $this->dispatch('--event', CatalogWasImported::class));

        $this->assertCount(1, $this->received);
        $this->assertInstanceOf(CatalogWasImported::class, $this->received[0]);
    }

    public function test_the_short_option_works_like_the_long_one()
    {
        $this->assertEquals(0, $this->dispatch('-e', CatalogWasImported::class));

        $this->assertCount(1, $this->received);
    }

    public function test_an_event_is_dispatched_from_its_short_class_name()
    {
        $this->assertEquals(0, $this->dispatch('--event', 'CatalogWasImported'));

        $this->assertCount(1, $this->received);
        $this->assertInstanceOf(CatalogWasImported::class, $this->received[0]);
    }

    public function test_the_framework_post_deployment_event_can_be_dispatched()
    {
        $this->assertEquals(0, $this->dispatch('--event', 'PostDeployment'));

        $this->assertCount(1, $this->received);
        $this->assertInstanceOf(PostDeployment::class, $this->received[0]);
    }

    public function test_a_missing_event_option_aborts_with_status_1()
    {
        $this->expectOutputRegex('/-e\|--event/');

        $this->assertEquals(1, $this->dispatch());
        $this->assertEmpty($this->received);
    }

    public function test_an_unknown_event_name_aborts_with_status_2()
    {
        $this->expectOutputRegex('/\[ProductWasLost\]/');

        $this->assertEquals(2, $this->dispatch('--event', 'ProductWasLost'));
        $this->assertEmpty($this->received);
    }

    public function test_a_class_that_is_not_an_event_aborts_with_status_2()
    {
        $this->expectOutputRegex('/does not extend from Event/');

        $this->assertEquals(2, $this->dispatch('--event', SayHello::class));
        $this->assertEmpty($this->received);
    }

    public function test_call_dispatches_through_the_global_events_instance()
    {
        $this->events->asGlobalInstance(function () {
            $this->assertEquals(0, Dispatch::call(Args::fromArgv(['--event', CatalogWasImported::class])));
        });

        $this->assertCount(1, $this->received);
    }
}
