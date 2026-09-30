<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Injector;
use Cube\Data\Bunch;
use Cube\Tests\Units\Core\Classes\Bird;
use Cube\Tests\Units\Core\Classes\Cat;
use Cube\Tests\Units\Core\Classes\Cattery;
use Cube\Tests\Units\Core\Classes\Collar;
use Cube\Tests\Units\Core\Classes\Common;
use Cube\Tests\Units\Core\Classes\DefaultParamClass;
use Cube\Tests\Units\Core\Classes\Dog;
use Cube\Tests\Units\Core\Classes\Dragon;
use Cube\Tests\Units\Core\Classes\Home;
use Cube\Tests\Units\Core\Classes\Kennel;
use Cube\Tests\Units\Core\Classes\RedCollar;
use Cube\Tests\Units\Core\Classes\Shelter;
use Cube\Tests\Units\Core\Classes\StrangeGroup;
use Cube\Tests\Units\Core\Classes\StrangeGroupVariadic;
use Cube\Tests\Units\Core\Classes\Zombie;
use Cube\Tests\Units\Core\Contracts\CanFitInAHouse;
use Cube\Tests\Units\Core\Contracts\CanFly;
use Cube\Tests\Units\Core\Contracts\CanTalk;
use Cube\Tests\Units\Core\Contracts\CanWalk;
use Cube\Tests\Units\Core\Contracts\Pet;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InjectorTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector();
    }

    public static function processCanFly(CanFly ...$thingsThatCanFly) {}
    public static function processCanTalk(CanTalk ...$thingsThatCanTalk){}
    public static function processCanFitInAHouse(CanFitInAHouse ...$thingsThatCanFitInAHouse){}
    public static function processCanWalk(CanWalk ...$thingsThatCanWalk){}

    public static function processAll(Common ...$allThings){}

    public function test_interface_variadic_injection() {
        $reduceClassesFor = function($callable) {
            $objects = $this->injector->getDependencies($callable);
            return Bunch::of($objects)->filter()->map(fn($x) => $x::class)->sort()->toArray();
        };

        $this->assertEquals([Bird::class, Dragon::class], $reduceClassesFor([self::class, 'processCanFly']));
        $this->assertEquals([Dragon::class], $reduceClassesFor([self::class, 'processCanTalk']));
        $this->assertEquals([Bird::class, Zombie::class], $reduceClassesFor([self::class, 'processCanFitInAHouse']));
        $this->assertEquals([Dragon::class, Zombie::class], $reduceClassesFor([self::class, 'processCanWalk']));
    }

    public function test_extends_variadic_injection() {
        $reduceClassesFor = function($callable) {
            $objects = $this->injector->getDependencies($callable);
            return Bunch::of($objects)->map(fn($x) => $x::class)->sort()->toArray();
        };

        $this->assertEquals([Bird::class, Dragon::class, Zombie::class], $reduceClassesFor([self::class, 'processAll']));
    }

    public function test_can_instanciate_simple_object() {
        $function = function(Bird $bird) {};
        $params = $this->injector->getDependencies($function);

        $this->assertCount(1, $params);
        $this->assertInstanceOf(Bird::class, $params[0]);

        $instance = $this->injector->instanciate(Bird::class);
        $this->assertInstanceOf(Bird::class, $instance);
    }

    public function test_can_instanciate_complex_object() {
        $strangeGroup = $this->injector->instanciate(StrangeGroup::class);
        $this->assertInstanceOf(StrangeGroup::class, $strangeGroup);

        $strangeGroupVariadic = $this->injector->instanciate(StrangeGroupVariadic::class);
        $this->assertInstanceOf(StrangeGroupVariadic::class, $strangeGroupVariadic);
    }

    public function test_injector_can_handle_optionnal_parameters() {
        $paramClass = $this->injector->instanciate(DefaultParamClass::class, ['some-name']);
        $this->assertInstanceOf(DefaultParamClass::class, $paramClass);
    }

    public function test_a_null_given_on_purpose_is_a_value() {
        $function = function(?string $label='from-the-signature') {};

        $this->assertEquals([null], $this->injector->getDependencies($function, [null]));
        $this->assertEquals(['given'], $this->injector->getDependencies($function, ['given']));
        $this->assertEquals(['from-the-signature'], $this->injector->getDependencies($function));
    }

    public function test_union_typed_parameter_keeps_the_given_value() {
        $function = function(string|int $value) {};

        $this->assertEquals([12], $this->injector->getDependencies($function, [12]));
    }

    public function test_union_typed_parameter_falls_back_to_its_default() {
        $function = function(string|int $value=5) {};

        $this->assertEquals([5], $this->injector->getDependencies($function));
    }

    public function test_union_typed_parameter_without_any_value_is_reported() {
        $function = function(string|int $value) {};

        $this->expectException(\InvalidArgumentException::class);
        $this->injector->getDependencies($function);
    }

    public static function processPets(Pet ...$pets) {}

    public function test_provided_instance_is_returned_by_instanciate() {
        $collar = new RedCollar();
        $this->injector->provide(Collar::class, $collar);

        $this->assertSame($collar, $this->injector->instanciate(Collar::class));
    }

    public function test_providers_are_not_shared_between_instances() {
        $this->injector->provide(Collar::class, new RedCollar());

        $this->assertNotInstanceOf(RedCollar::class, (new Injector())->instanciate(Collar::class));
    }

    public function test_provided_instance_is_injected_as_constructor_dependency() {
        $collar = new RedCollar();
        $this->injector->provide(Collar::class, $collar);

        $this->assertSame($collar, $this->injector->instanciate(Kennel::class)->collar);
    }

    public function test_provided_instance_is_injected_in_a_closure() {
        $collar = new RedCollar();
        $this->injector->provide(Collar::class, $collar);

        $this->assertSame([$collar], $this->injector->getDependencies(function(Collar $collar) {}));
    }

    public function test_provided_callback_receives_the_requesting_class() {
        $callers = [];
        $this->injector->provide(Collar::class, function(?string $caller) use (&$callers) {
            $callers[] = $caller;
            return $caller === Kennel::class ? new RedCollar() : new Collar();
        });

        $this->assertInstanceOf(RedCollar::class, $this->injector->instanciate(Kennel::class)->collar);

        $catteryCollar = $this->injector->instanciate(Cattery::class)->collar;
        $this->assertInstanceOf(Collar::class, $catteryCollar);
        $this->assertNotInstanceOf(RedCollar::class, $catteryCollar);

        $this->assertEquals([Kennel::class, Cattery::class], $callers);
    }

    public function test_provided_callback_receives_the_class_of_a_method_callback() {
        $callers = [];
        $this->injector->provide(Collar::class, function(?string $caller) use (&$callers) {
            $callers[] = $caller;
            return new Collar();
        });

        $this->injector->getDependencies([self::class, 'processCollar']);

        $this->assertEquals([self::class], $callers);
    }

    public static function processCollar(Collar $collar) {}

    public function test_provided_callback_receives_null_when_nothing_requested_it() {
        $callers = [];
        $this->injector->provide(Collar::class, function(?string $caller) use (&$callers) {
            $callers[] = $caller;
            return new Collar();
        });

        $this->injector->instanciate(Collar::class);
        $this->injector->getDependencies(function(Collar $collar) {});

        $this->assertEquals([null, null], $callers);
    }

    public function test_provided_instance_is_injected_for_an_interface_typed_parameter() {
        $cat = new Cat();
        $this->injector->provide(Pet::class, $cat);

        $this->assertSame($cat, $this->injector->instanciate(Home::class)->pet);
    }

    public function test_provided_value_not_extending_the_requested_class_is_rejected() {
        $this->injector->provide(Collar::class, new Cat());

        $this->expectException(RuntimeException::class);
        $this->injector->instanciate(Kennel::class);
    }

    public function test_provided_value_not_implementing_the_requested_interface_is_rejected() {
        $this->injector->provide(Pet::class, new Collar());

        $this->expectException(RuntimeException::class);
        $this->injector->instanciate(Home::class);
    }

    public function test_variadic_values_are_discovered_without_provider() {
        $this->assertEqualsCanonicalizing(
            [Cat::class, Dog::class],
            Bunch::of($this->injector->getDependencies([self::class, 'processPets']))->map(fn($x) => $x::class)->toArray()
        );
    }

    public function test_provided_variadic_values_replace_discovery() {
        $cat = new Cat();
        $this->injector->provideVariadic(Pet::class, [$cat]);

        $this->assertSame([$cat], $this->injector->getDependencies([self::class, 'processPets']));
        $this->assertSame([$cat], $this->injector->instanciate(Shelter::class)->pets);
    }

    public function test_provided_variadic_callback_receives_the_requesting_class() {
        $cat = new Cat();
        $dog = new Dog();
        $this->injector->provideVariadic(Pet::class, fn(?string $caller) => $caller === Shelter::class ? [$cat, $dog] : [$dog]);

        $this->assertSame([$cat, $dog], $this->injector->instanciate(Shelter::class)->pets);
        $this->assertSame([$dog], $this->injector->getDependencies([self::class, 'processPets']));
    }

    public function test_provided_variadic_value_not_implementing_the_interface_is_rejected() {
        $this->injector->provideVariadic(Pet::class, [new Cat(), new Collar()]);

        $this->expectException(RuntimeException::class);
        $this->injector->instanciate(Shelter::class);
    }
}
