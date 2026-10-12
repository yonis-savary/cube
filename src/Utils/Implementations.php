<?php

namespace Cube\Utils;

use Cube\Core\Autoloader;
use Cube\Core\Exceptions\ImplementationNotFoundException;
use Cube\Core\Injector;
use Cube\Data\Bunch;
use LogicException;

/**
 * Picks the implementations of an abstraction that support a given case : each candidate class is
 * asked first, and only the ones it keeps are built (through the `Injector`).
 *
 * ```php
 * $exporter = Implementations::findOrFail(
 *  InvoiceExporter::class,
 *  fn (string $class) => $class::supports($format)
 * );
 * ```
 */
class Implementations
{
    /**
     * @template T of object
     *
     * @param class-string<T> $base
     * @param callable(class-string<T>):bool $supports
     *
     * @return ?T
     */
    public static function find(string $base, callable $supports, array $constructorArgs = []): ?object
    {
        $candidate = self::candidatesOf($base)->first($supports);

        return $candidate
            ? Injector::getInstance()->instanciate($candidate, $constructorArgs)
            : null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $base
     * @param callable(class-string<T>):bool $supports
     *
     * @return T
     *
     * @throws ImplementationNotFoundException
     */
    public static function findOrFail(string $base, callable $supports, array $constructorArgs = [], ?string $for = null): object
    {
        return self::find($base, $supports, $constructorArgs)
            ?? throw new ImplementationNotFoundException($base, self::candidatesOf($base), $for);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $base
     * @param callable(class-string<T>):bool $supports
     *
     * @return Bunch<int,T>
     */
    public static function findAll(string $base, callable $supports, array $constructorArgs = []): Bunch
    {
        $injector = Injector::getInstance();
        return self::candidatesOf($base)
            ->filter($supports, true)
            ->map(fn($candidate) => $injector->instanciate($candidate, $constructorArgs));
    }

    /**
     * Like `find()`, for a case only one implementation may answer : two of them is a mistake, not a choice
     *
     * @template T of object
     *
     * @param class-string<T> $base
     * @param callable(class-string<T>):bool $supports
     *
     * @return ?T
     *
     * @throws LogicException
     */
    public static function findSingle(string $base, callable $supports, array $constructorArgs = [], ?string $for = null): ?object
    {
        $candidates = self::candidatesOf($base)->filter($supports, true);

        if ($candidates->count() > 1)
            throw new LogicException('Several '.$base.' answer for '.($for ?? 'the same case').' : '.$candidates->join(', ').', only one may');

        return ($candidate = $candidates->first())
            ? Injector::getInstance()->instanciate($candidate, $constructorArgs)
            : null;
    }

    /**
     * @template TClassOrInterface
     * @param class-string<TClassOrInterface> $base
     *
     * @return Bunch<int,class-string<TClassOrInterface>>
     */
    protected static function candidatesOf(string $base): Bunch
    {
        return interface_exists($base)
            ? Bunch::of(Autoloader::classesThatImplements($base))
            : Bunch::of(Autoloader::classesThatExtends($base));
    }
}
