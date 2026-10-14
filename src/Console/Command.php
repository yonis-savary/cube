<?php

namespace Cube\Console;

use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Data\Bunch;
use Cube\Utils\Console;

abstract class Command
{
    public static function call(?Args $args = null): int
    {
        return Injector::getInstance()
            ->instanciate(static::class)
            ->execute($args ?? new Args);
    }

    public function abort(string $errorMessage, int $returnCode = 1): int {
        Console::print($errorMessage);
        return $returnCode;
    }

    public function getHelp(): string
    {
        return 'Please write a help section for this command';
    }

    /**
     * @return string|string[]
     */
    public function getManual(): string|array {
        return $this->getFullIdentifier() . " - No manual given";
    }


    final public function getFullIdentifier(): string
    {
        return $this->getScope().':'.$this->getName();
    }

    public function getName(): string
    {
        return static::class
            |> (fn ($class) => preg_replace('/.+\\\/', '', $class))
            |> (fn ($class) => preg_replace_callback('/([a-z])([A-Z])/', fn ($m) => $m[1].'-'.$m[2], $class))
            |> strtolower(...);
    }

    public function getScope(): string
    {
        return static::class
            |> (fn ($class) => preg_replace('/\\\.+/', '', $class))
            |> (fn ($class) => preg_replace_callback('/([a-z])([A-Z])/', fn ($m) => $m[1].'-'.$m[2], $class))
            |> strtolower(...);
    }

    /**
     * @template TBase
     *
     * @param class-string<TBase> $base
     *
     * @return Bunch<int,class-string<TBase>>
     */
    protected function classesNamed(string $base, string $name): Bunch
    {
        $name = ltrim($name, '\\');

        return Bunch::wrap(Autoloader::classesThatExtends($base))
            ->filter(fn (string $class) => $class === $name || str_ends_with($class, '\\'.$name))
            ->values();
    }

    abstract public function execute(Args $args): int;
}
