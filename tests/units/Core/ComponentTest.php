<?php

namespace Cube\Tests\Units\Core;

use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Tests\Units\Core\Classes\SpecializedCounter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function test_instance_is_built_once_and_reused()
    {
        $this->assertFalse(Counter::hasInstance());

        $first = Counter::getInstance();

        $this->assertTrue(Counter::hasInstance());
        $this->assertSame($first, Counter::getInstance());
    }

    public function test_default_instance_is_built_through_the_injector()
    {
        $this->assertEquals('default', Counter::getInstance()->tag);
    }

    public function test_set_instance_replaces_the_current_one()
    {
        Counter::setInstance(new Counter('replaced'));

        $this->assertEquals('replaced', Counter::getInstance()->tag);
    }

    public function test_remove_instance_gives_back_a_fresh_default()
    {
        Counter::setInstance(new Counter('replaced'));
        Counter::removeInstance();

        $this->assertFalse(Counter::hasInstance());
        $this->assertEquals('default', Counter::getInstance()->tag);
    }

    public function test_a_subclass_keeps_its_own_instance()
    {
        $specialized = SpecializedCounter::getInstance();
        $base = Counter::getInstance();

        $this->assertInstanceOf(SpecializedCounter::class, $specialized);
        $this->assertNotInstanceOf(SpecializedCounter::class, $base);
        $this->assertNotSame($specialized, $base);
    }

    public function test_with_instance_scopes_then_restores()
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

    public function test_with_instance_hands_both_instances_to_the_callback()
    {
        $original = new Counter('original');
        $scoped = new Counter('scoped');
        Counter::setInstance($original);

        Counter::withInstance($scoped, function ($given, $previous) use ($original, $scoped) {
            $this->assertSame($scoped, $given);
            $this->assertSame($original, $previous);
        });
    }

    public function test_with_instance_restores_even_when_the_callback_throws()
    {
        $original = new Counter('original');
        Counter::setInstance($original);

        try {
            Counter::withInstance(new Counter('scoped'), function () {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $_) {
        }

        $this->assertSame($original, Counter::getInstance());
    }

    public function test_with_instance_leaves_no_default_instance_behind()
    {
        $this->assertFalse(Counter::hasInstance());

        Counter::withInstance(new Counter('scoped'), fn () => null);

        $this->assertFalse(Counter::hasInstance());
    }

    public function test_as_global_instance_scopes_the_object_it_is_called_on()
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
