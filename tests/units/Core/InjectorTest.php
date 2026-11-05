<?php

namespace Cube\Tests\Units\Core;

use Cube\Core\Injector;
use Cube\Data\Bunch;
use Cube\Env\Logger\Logger;
use Cube\Tests\Units\Core\Classes\Bird;
use Cube\Tests\Units\Core\Classes\Canary;
use Cube\Tests\Units\Core\Classes\Cat;
use Cube\Tests\Units\Core\Classes\Cattery;
use Cube\Tests\Units\Core\Classes\Collar;
use Cube\Tests\Units\Core\Classes\Common;
use Cube\Tests\Units\Core\Classes\Counter;
use Cube\Tests\Units\Core\Classes\DefaultParamClass;
use Cube\Tests\Units\Core\Classes\Dog;
use Cube\Tests\Units\Core\Classes\Dragon;
use Cube\Tests\Units\Core\Classes\Home;
use Cube\Tests\Units\Core\Classes\Kennel;
use Cube\Tests\Units\Core\Classes\Logbook;
use Cube\Tests\Units\Core\Classes\RedCollar;
use Cube\Tests\Units\Core\Classes\Shelter;
use Cube\Tests\Units\Core\Classes\SpecializedCounter;
use Cube\Tests\Units\Core\Classes\StrangeGroup;
use Cube\Tests\Units\Core\Classes\StrangeGroupVariadic;
use Cube\Tests\Units\Core\Classes\Zombie;
use Cube\Tests\Units\Core\Contracts\CanFitInAHouse;
use Cube\Tests\Units\Core\Contracts\CanFly;
use Cube\Tests\Units\Core\Contracts\CanSing;
use Cube\Tests\Units\Core\Contracts\CanTalk;
use Cube\Tests\Units\Core\Contracts\CanWalk;
use Cube\Tests\Units\Core\Contracts\Pet;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class InjectorTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector();
    }

    public static function processCanFly(CanFly ...$thingsThatCanFly) {}

    public static function processCanTalk(CanTalk ...$thingsThatCanTalk) {}

    public static function processCanFitInAHouse(CanFitInAHouse ...$thingsThatCanFitInAHouse) {}

    public static function processCanWalk(CanWalk ...$thingsThatCanWalk) {}

    public static function processAll(Common ...$allThings) {}

    public function testInterfaceVariadicInjection()
    {
        $reduceClassesFor = function ($callable) {
            $objects = $this->injector->getDependencies($callable);
            return Bunch::wrap($objects)->filter()->map(fn ($x) => $x::class)->sort()->toArray();
        };

        $this->assertEquals([Bird::class, Dragon::class], $reduceClassesFor([self::class, 'processCanFly']));
        $this->assertEquals([Dragon::class], $reduceClassesFor([self::class, 'processCanTalk']));
        $this->assertEquals([Bird::class, Zombie::class], $reduceClassesFor([self::class, 'processCanFitInAHouse']));
        $this->assertEquals([Dragon::class, Zombie::class], $reduceClassesFor([self::class, 'processCanWalk']));
    }

    public function testExtendsVariadicInjection()
    {
        $reduceClassesFor = function ($callable) {
            $objects = $this->injector->getDependencies($callable);
            return Bunch::wrap($objects)->map(fn ($x) => $x::class)->sort()->toArray();
        };

        $this->assertEquals([Bird::class, Dragon::class, Zombie::class], $reduceClassesFor([self::class, 'processAll']));
    }

    public function testCanInstanciateSimpleObject()
    {
        $function = function (Bird $bird) {};
        $params = $this->injector->getDependencies($function);

        $this->assertCount(1, $params);
        $this->assertInstanceOf(Bird::class, $params[0]);

        $instance = $this->injector->instanciate(Bird::class);
        $this->assertInstanceOf(Bird::class, $instance);
    }

    public function testCanInstanciateComplexObject()
    {
        $strangeGroup = $this->injector->instanciate(StrangeGroup::class);
        $this->assertInstanceOf(StrangeGroup::class, $strangeGroup);

        $strangeGroupVariadic = $this->injector->instanciate(StrangeGroupVariadic::class);
        $this->assertInstanceOf(StrangeGroupVariadic::class, $strangeGroupVariadic);
    }

    public function testInjectorCanHandleOptionnalParameters()
    {
        $paramClass = $this->injector->instanciate(DefaultParamClass::class, ['some-name']);
        $this->assertInstanceOf(DefaultParamClass::class, $paramClass);
    }

    public function testANullGivenOnPurposeIsAValue()
    {
        $function = function (?string $label = 'from-the-signature') {};

        $this->assertEquals([null], $this->injector->getDependencies($function, [null]));
        $this->assertEquals(['given'], $this->injector->getDependencies($function, ['given']));
        $this->assertEquals(['from-the-signature'], $this->injector->getDependencies($function));
    }

    public function testUnionTypedParameterKeepsTheGivenValue()
    {
        $function = function (int|string $value) {};

        $this->assertEquals([12], $this->injector->getDependencies($function, [12]));
    }

    public function testUnionTypedParameterFallsBackToItsDefault()
    {
        $function = function (int|string $value = 5) {};

        $this->assertEquals([5], $this->injector->getDependencies($function));
    }

    public function testUnionTypedParameterWithoutAnyValueIsReported()
    {
        $function = function (int|string $value) {};

        $this->expectException(\InvalidArgumentException::class);
        $this->injector->getDependencies($function);
    }

    public static function processPets(Pet ...$pets) {}

    public function testProvidedInstanceIsReturnedByInstanciate()
    {
        $collar = new RedCollar();
        $this->injector->provide(Collar::class, $collar);

        $this->assertSame($collar, $this->injector->instanciate(Collar::class));
    }

    public function testProvidersAreNotSharedBetweenInstances()
    {
        $this->injector->provide(Collar::class, new RedCollar());

        $this->assertNotInstanceOf(RedCollar::class, (new Injector())->instanciate(Collar::class));
    }

    public function testProvidedInstanceIsInjectedAsConstructorDependency()
    {
        $collar = new RedCollar();
        $this->injector->provide(Collar::class, $collar);

        $this->assertSame($collar, $this->injector->instanciate(Kennel::class)->collar);
    }

    public function testProvidedInstanceIsInjectedInAClosure()
    {
        $collar = new RedCollar();
        $this->injector->provide(Collar::class, $collar);

        $this->assertSame([$collar], $this->injector->getDependencies(function (Collar $collar) {}));
    }

    public function testProvidedCallbackReceivesTheRequestingClass()
    {
        $callers = [];
        $this->injector->provide(Collar::class, function (?string $caller) use (&$callers) {
            $callers[] = $caller;
            return Kennel::class === $caller ? new RedCollar() : new Collar();
        });

        $this->assertInstanceOf(RedCollar::class, $this->injector->instanciate(Kennel::class)->collar);

        $catteryCollar = $this->injector->instanciate(Cattery::class)->collar;
        $this->assertInstanceOf(Collar::class, $catteryCollar);
        $this->assertNotInstanceOf(RedCollar::class, $catteryCollar);

        $this->assertEquals([Kennel::class, Cattery::class], $callers);
    }

    public function testProvidedCallbackReceivesTheClassOfAMethodCallback()
    {
        $callers = [];
        $this->injector->provide(Collar::class, function (?string $caller) use (&$callers) {
            $callers[] = $caller;
            return new Collar();
        });

        $this->injector->getDependencies([self::class, 'processCollar']);

        $this->assertEquals([self::class], $callers);
    }

    public static function processCollar(Collar $collar) {}

    public function testProvidedCallbackReceivesNullWhenNothingRequestedIt()
    {
        $callers = [];
        $this->injector->provide(Collar::class, function (?string $caller) use (&$callers) {
            $callers[] = $caller;
            return new Collar();
        });

        $this->injector->instanciate(Collar::class);
        $this->injector->getDependencies(function (Collar $collar) {});

        $this->assertEquals([null, null], $callers);
    }

    public function testProvidedInstanceIsInjectedForAnInterfaceTypedParameter()
    {
        $cat = new Cat();
        $this->injector->provide(Pet::class, $cat);

        $this->assertSame($cat, $this->injector->instanciate(Home::class)->pet);
    }

    public function testProvidedValueNotExtendingTheRequestedClassIsRejected()
    {
        $this->injector->provide(Collar::class, new Cat());

        $this->expectException(\RuntimeException::class);
        $this->injector->instanciate(Kennel::class);
    }

    public function testProvidedValueNotImplementingTheRequestedInterfaceIsRejected()
    {
        $this->injector->provide(Pet::class, new Collar());

        $this->expectException(\RuntimeException::class);
        $this->injector->instanciate(Home::class);
    }

    public function testVariadicValuesAreDiscoveredWithoutProvider()
    {
        $this->assertEqualsCanonicalizing(
            [Cat::class, Dog::class],
            Bunch::wrap($this->injector->getDependencies([self::class, 'processPets']))->map(fn ($x) => $x::class)->toArray()
        );
    }

    public function testProvidedVariadicValuesReplaceDiscovery()
    {
        $cat = new Cat();
        $this->injector->provideVariadic(Pet::class, [$cat]);

        $this->assertSame([$cat], $this->injector->getDependencies([self::class, 'processPets']));
        $this->assertSame([$cat], $this->injector->instanciate(Shelter::class)->pets);
    }

    public function testProvidedVariadicCallbackReceivesTheRequestingClass()
    {
        $cat = new Cat();
        $dog = new Dog();
        $this->injector->provideVariadic(Pet::class, fn (?string $caller) => Shelter::class === $caller ? [$cat, $dog] : [$dog]);

        $this->assertSame([$cat, $dog], $this->injector->instanciate(Shelter::class)->pets);
        $this->assertSame([$dog], $this->injector->getDependencies([self::class, 'processPets']));
    }

    public function testProvidedVariadicValueNotImplementingTheInterfaceIsRejected()
    {
        $this->injector->provideVariadic(Pet::class, [new Cat(), new Collar()]);

        $this->expectException(\RuntimeException::class);
        $this->injector->instanciate(Shelter::class);
    }

    /** The PSR LoggerInterface provision instanciates Logger, whose constructor needs a file name. */
    public function testAPsrLoggerInterfaceIsResolvedToCubeLogger()
    {
        $this->assertInstanceOf(Logger::class, Injector::getInstance()->instanciate(Logbook::class)->logger);
    }

    public function testAProvidedComponentNameGivesItsAmbientInstance()
    {
        $ambient = new SpecializedCounter('ambient');

        SpecializedCounter::withInstance($ambient, function () use ($ambient) {
            $this->injector->provide(Counter::class, SpecializedCounter::class);

            $this->assertSame($ambient, $this->injector->instanciate(Counter::class));
        });
    }

    /** A provided invokable object is called as if it were a resolving callback. */
    public function testAProvidedInvokableObjectIsHandedAsIs()
    {
        $canary = new Canary();
        $this->injector->provide(CanSing::class, $canary);

        $this->assertSame($canary, $this->injector->instanciate(CanSing::class));
    }

    /** A provided scalar reaches $provided::class, which throws a TypeError on a non-object. */
    public function testAProvidedScalarIsRejectedLikeAMismatchingObject()
    {
        $this->injector->provide(Pet::class, 42);

        $this->expectException(\RuntimeException::class);
        $this->injector->instanciate(Pet::class);
    }
}
