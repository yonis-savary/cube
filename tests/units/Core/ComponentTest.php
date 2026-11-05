<?php

namespace Cube\Tests\Units\Core;

use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Tests\Units\Core\Classes\SpecializedCounter;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ComponentTest extends TestCase
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

    public function testInstanceIsBuiltOnceAndReused()
    {
        $this->assertFalse(Counter::hasInstance());

        $first = Counter::getInstance();

        $this->assertTrue(Counter::hasInstance());
        $this->assertSame($first, Counter::getInstance());
    }

    public function testDefaultInstanceIsBuiltThroughTheInjector()
    {
        $this->assertEquals('default', Counter::getInstance()->tag);
    }

    public function testSetInstanceReplacesTheCurrentOne()
    {
        Counter::setInstance(new Counter('replaced'));

        $this->assertEquals('replaced', Counter::getInstance()->tag);
    }

    public function testRemoveInstanceGivesBackAFreshDefault()
    {
        Counter::setInstance(new Counter('replaced'));
        Counter::removeInstance();

        $this->assertFalse(Counter::hasInstance());
        $this->assertEquals('default', Counter::getInstance()->tag);
    }

    public function testASubclassKeepsItsOwnInstance()
    {
        $specialized = SpecializedCounter::getInstance();
        $base = Counter::getInstance();

        $this->assertInstanceOf(SpecializedCounter::class, $specialized);
        $this->assertNotInstanceOf(SpecializedCounter::class, $base);
        $this->assertNotSame($specialized, $base);
    }

    public function testWithInstanceScopesThenRestores()
    {
        $original = new Counter('original');
        Counter::setInstance($original);

        $seenInside = null;
        Counter::withInstance(new Counter('scoped'), function () use (&$seenInside) {
            $seenInside = Counter::getInstance()->tag;
        });

        $this->assertEquals('scoped', $seenInside);
        $this->assertSame($original, Counter::getInstance());
    }

    public function testWithInstanceHandsBothInstancesToTheCallback()
    {
        $original = new Counter('original');
        $scoped = new Counter('scoped');
        Counter::setInstance($original);

        Counter::withInstance($scoped, function ($given, $previous) use ($original, $scoped) {
            $this->assertSame($scoped, $given);
            $this->assertSame($original, $previous);
        });
    }

    public function testWithInstanceRestoresEvenWhenTheCallbackThrows()
    {
        $original = new Counter('original');
        Counter::setInstance($original);

        try {
            Counter::withInstance(new Counter('scoped'), function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $_) {
        }

        $this->assertSame($original, Counter::getInstance());
    }

    public function testWithInstanceLeavesNoDefaultInstanceBehind()
    {
        $this->assertFalse(Counter::hasInstance());

        Counter::withInstance(new Counter('scoped'), fn () => null);

        $this->assertFalse(Counter::hasInstance());
    }

    public function testAsGlobalInstanceScopesTheObjectItIsCalledOn()
    {
        $scoped = new Counter('scoped');

        $seenInside = null;
        $scoped->asGlobalInstance(function () use (&$seenInside) {
            $seenInside = Counter::getInstance();
        });

        $this->assertSame($scoped, $seenInside);
        $this->assertFalse(Counter::hasInstance());
    }
}
