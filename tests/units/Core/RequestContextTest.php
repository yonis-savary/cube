<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Injector;
use Cube\Core\RequestContext;
use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Tests\Units\Core\Classes\SpecializedCounter;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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

    public function testRunReturnsTheCallbackResult()
    {
        $context = new RequestContext();

        $this->assertEquals('result', $context->run(fn () => 'result'));
    }

    public function testInstancesExistingBeforeRunAreHiddenFromTheCallback()
    {
        Counter::setInstance(new Counter('before'));

        $context = new RequestContext();
        $hadInstance = $context->run(fn () => Counter::hasInstance());

        $this->assertFalse($hadInstance);
    }

    public function testInstancesExistingBeforeRunAreRestoredAfter()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        (new RequestContext())->run(fn () => Counter::getInstance());

        $this->assertSame($before, Counter::getInstance());
    }

    public function testInstancesReplacedDuringRunAreRestoredAfter()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        (new RequestContext())->run(fn () => Counter::setInstance(new Counter('during')));

        $this->assertSame($before, Counter::getInstance());
    }

    public function testComponentsCreatedDuringRunAreReset()
    {
        $context = new RequestContext();

        $context->run(fn () => Counter::getInstance());

        $this->assertFalse(Counter::hasInstance());
    }

    public function testPersistentComponentsKeepTheirInstance()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        $context = new RequestContext([Counter::class]);
        $seenInside = $context->run(fn () => Counter::getInstance());

        $this->assertSame($before, $seenInside);
        $this->assertSame($before, Counter::getInstance());
    }

    public function testPersistentComponentsCreatedDuringRunAreKept()
    {
        $context = new RequestContext([Counter::class]);

        $instance = $context->run(fn () => Counter::getInstance());

        $this->assertSame($instance, Counter::getInstance());
    }

    /** The Injector was reset like any component, so a run lost every provide() binding, the framework's own included. */
    public function testTheInjectorAndItsBindingsAreKept()
    {
        $injector = Injector::getInstance();

        $seenInside = RequestContext::oneShot(fn () => Injector::getInstance());

        $this->assertSame($injector, $seenInside);
    }

    public function testPersistenceIsNotInheritedBySubclasses()
    {
        $context = new RequestContext([Counter::class]);

        $context->run(function () {
            Counter::getInstance();
            SpecializedCounter::getInstance();
        });

        $this->assertTrue(Counter::hasInstance());
        $this->assertFalse(SpecializedCounter::hasInstance());
    }

    public function testComponentsAreRestoredEvenWhenTheCallbackThrows()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        $context = new RequestContext();

        try {
            $context->run(function () {
                Counter::setInstance(new Counter('during'));

                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $_) {
        }

        $this->assertSame($before, Counter::getInstance());
    }

    public function testNestedRunsRestoreEachLevel()
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

    public function testOneShotRunsTheCallbackWithAFreshContext()
    {
        $received = null;

        $result = RequestContext::oneShot(function (RequestContext $context) use (&$received) {
            $received = $context;

            return 'result';
        });

        $this->assertEquals('result', $result);
        $this->assertInstanceOf(RequestContext::class, $received);
    }

    public function testOneShotGivesComponentsBackTheirPreviousInstance()
    {
        $before = new Counter('before');
        Counter::setInstance($before);

        $seenInside = RequestContext::oneShot(fn () => Counter::getInstance());

        $this->assertNotSame($before, $seenInside);
        $this->assertSame($before, Counter::getInstance());
    }

    public function testOneShotKeepsPersistentComponents()
    {
        $instance = RequestContext::oneShot(fn () => Counter::getInstance(), [Counter::class]);

        $this->assertSame($instance, Counter::getInstance());
    }
}
