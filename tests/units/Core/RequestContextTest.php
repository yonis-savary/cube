<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\RequestContext;
use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Tests\Units\Core\Classes\SpecializedCounter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RequestContextTest extends TestCase
{
    protected function setUp(): void
    {
        Counter::removeInstance();
        SpecializedCounter::removeInstance();
    }

    protected function tearDown(): void
    {
        Counter::removeInstance();
        SpecializedCounter::removeInstance();
    }

    public function test_run_returns_the_callback_result()
    {
        $context = new RequestContext();

        $this->assertEquals('result', $context->run(fn () => 'result'));
    }

    public function test_instances_existing_before_run_are_hidden_from_the_callback()
    {
        Counter::setInstance(new Counter('before'));

        $context = new RequestContext();
        $hadInstance = $context->run(fn () => Counter::hasInstance());

        $this->assertFalse($hadInstance);
    }

    public function test_instances_existing_before_run_are_restored_after()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        (new RequestContext())->run(fn () => Counter::getInstance());

        $this->assertSame($before, Counter::getInstance());
    }

    public function test_instances_replaced_during_run_are_restored_after()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        (new RequestContext())->run(fn () => Counter::setInstance(new Counter('during')));

        $this->assertSame($before, Counter::getInstance());
    }

    public function test_components_created_during_run_are_reset()
    {
        $context = new RequestContext();

        $context->run(fn () => Counter::getInstance());

        $this->assertFalse(Counter::hasInstance());
    }

    public function test_persistent_components_keep_their_instance()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        $context = new RequestContext([Counter::class]);
        $seenInside = $context->run(fn () => Counter::getInstance());

        $this->assertSame($before, $seenInside);
        $this->assertSame($before, Counter::getInstance());
    }

    public function test_persistent_components_created_during_run_are_kept()
    {
        $context = new RequestContext([Counter::class]);

        $instance = $context->run(fn () => Counter::getInstance());

        $this->assertSame($instance, Counter::getInstance());
    }

    public function test_persistence_is_not_inherited_by_subclasses()
    {
        $context = new RequestContext([Counter::class]);

        $context->run(function () {
            Counter::getInstance();
            SpecializedCounter::getInstance();
        });

        $this->assertTrue(Counter::hasInstance());
        $this->assertFalse(SpecializedCounter::hasInstance());
    }

    public function test_components_are_restored_even_when_the_callback_throws()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        $context = new RequestContext();

        try {
            $context->run(function () {
                Counter::setInstance(new Counter('during'));

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $_) {
        }

        $this->assertSame($before, Counter::getInstance());
    }

    public function test_nested_runs_restore_each_level()
    {
        $outer = new Counter('outer');
        $inner = new Counter('inner');
        Counter::setInstance($outer);

        $context = new RequestContext();
        $seenAfterInnerRun = $context->run(function () use ($context, $inner) {
            Counter::setInstance($inner);
            $context->run(fn () => Counter::setInstance(new Counter('deepest')));

            return Counter::getInstance();
        });

        $this->assertSame($inner, $seenAfterInnerRun);
        $this->assertSame($outer, Counter::getInstance());
    }
}
