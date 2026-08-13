<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Injector;
use Cube\Data\Bunch;
use Cube\Tests\Units\Core\Classes\Bird;
use Cube\Tests\Units\Core\Classes\Common;
use Cube\Tests\Units\Core\Classes\DefaultParamClass;
use Cube\Tests\Units\Core\Classes\Dragon;
use Cube\Tests\Units\Core\Classes\StrangeGroup;
use Cube\Tests\Units\Core\Classes\StrangeGroupVariadic;
use Cube\Tests\Units\Core\Classes\Zombie;
use Cube\Tests\Units\Core\Contracts\CanFitInAHouse;
use Cube\Tests\Units\Core\Contracts\CanFly;
use Cube\Tests\Units\Core\Contracts\CanTalk;
use Cube\Tests\Units\Core\Contracts\CanWalk;
use PHPUnit\Framework\TestCase;

class InjectorTest extends TestCase
{
    public static function processCanFly(CanFly ...$thingsThatCanFly) {}
    public static function processCanTalk(CanTalk ...$thingsThatCanTalk){}
    public static function processCanFitInAHouse(CanFitInAHouse ...$thingsThatCanFitInAHouse){}
    public static function processCanWalk(CanWalk ...$thingsThatCanWalk){}

    public static function processAll(Common ...$allThings){}

    public function test_interface_variadic_injection() {
        $reduceClassesFor = function($callable) {
            $objects = Injector::getDependencies($callable);
            return Bunch::of($objects)->map(fn($x) => $x::class)->sort()->toArray();
        };

        $this->assertEquals([Bird::class, Dragon::class], $reduceClassesFor([self::class, 'processCanFly']));
        $this->assertEquals([Dragon::class], $reduceClassesFor([self::class, 'processCanTalk']));
        $this->assertEquals([Bird::class, Zombie::class], $reduceClassesFor([self::class, 'processCanFitInAHouse']));
        $this->assertEquals([Dragon::class, Zombie::class], $reduceClassesFor([self::class, 'processCanWalk']));
    }

    public function test_extends_variadic_injection() {
        $reduceClassesFor = function($callable) {
            $objects = Injector::getDependencies($callable);
            return Bunch::of($objects)->map(fn($x) => $x::class)->sort()->toArray();
        };

        $this->assertEquals([Bird::class, Dragon::class, Zombie::class], $reduceClassesFor([self::class, 'processAll']));
    }

    public function test_can_instanciate_simple_object() {
        $function = function(Bird $bird) {};
        $params = Injector::getDependencies($function);

        $this->assertCount(1, $params);
        $this->assertInstanceOf(Bird::class, $params[0]);

        $instance = Injector::instanciate(Bird::class);
        $this->assertInstanceOf(Bird::class, $instance);
    }

    public function test_can_instanciate_complex_object() {
        $strangeGroup = Injector::instanciate(StrangeGroup::class);
        $this->assertInstanceOf(StrangeGroup::class, $strangeGroup);

        $strangeGroupVariadic = Injector::instanciate(StrangeGroupVariadic::class);
        $this->assertInstanceOf(StrangeGroupVariadic::class, $strangeGroupVariadic);
    }

    public function test_injector_can_handle_optionnal_parameters() {
        $paramClass = Injector::instanciate(DefaultParamClass::class, ['some-name']);
        $this->assertInstanceOf(DefaultParamClass::class, $paramClass);
    }

    public function test_a_null_given_on_purpose_is_a_value() {
        $function = function(?string $label='from-the-signature') {};

        $this->assertEquals([null], Injector::getDependencies($function, [null]));
        $this->assertEquals(['given'], Injector::getDependencies($function, ['given']));
        $this->assertEquals(['from-the-signature'], Injector::getDependencies($function));
    }

    public function test_union_typed_parameter_keeps_the_given_value() {
        $function = function(string|int $value) {};

        $this->assertEquals([12], Injector::getDependencies($function, [12]));
    }

    public function test_union_typed_parameter_falls_back_to_its_default() {
        $function = function(string|int $value=5) {};

        $this->assertEquals([5], Injector::getDependencies($function));
    }

    public function test_union_typed_parameter_without_any_value_is_reported() {
        $function = function(string|int $value) {};

        $this->expectException(\InvalidArgumentException::class);
        Injector::getDependencies($function);
    }
}