<?php

namespace Cube\Data;

use Closure;
use Countable;
use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Data\Classes\NoValue;
use Cube\Data\Database\Database;
use Cube\Utils\Utils;

/**
 * @template TKey
 * @template TValue
 */
class Bunch implements Countable
{
    /** @var array<TKey,TValue> */
    protected array $data;

    /**
     * @template TType
     *
     * @param array<TType> $initialData
     *
     * @return self<int,TType>
     */
    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }

    public function __clone()
    {
        return new self($this->get());
    }

    /**
     * @template TType
     * @template TTypeKey
     *
     * @param TType[]|Bunch<TTypeKey,TType>|TType $element
     *
     * @return self<int,TType>
     */
    public static function of(mixed $element): self
    {
        if ($element instanceof Bunch) {
            $element = $element->get();
        }

        if (!(is_array($element) && Utils::isList($element))) {
            $element = [$element];
        }

        return new self($element);
    }


    /**
     * @template TClassname
     * @param class-string<TClassname> $class
     * @return self<int,TClassname>
     */
    public static function fromExtends(string $class, array $constructorArgs=[]): self
    {
        return Bunch::of(Autoloader::classesThatExtends($class))->instanciates($constructorArgs);
    }

    /**
     * @template TClassname
     * @param class-string<TClassname> $class
     * @return self<int,TClassname>
     */
    public static function fromImplements(string $implements, array $constructorArgs=[]): self
    {
        return Bunch::of(Autoloader::classesThatImplements($implements))->instanciates($constructorArgs);
    }

    /**
     * @template TClassname
     * @param class-string<TClassname> $class
     * @return self<int,TClassname>
     */
    public static function fromUses(string $uses, array $constructorArgs=[]): self
    {
        return Bunch::of(Autoloader::classesThatUses($uses))->instanciates($constructorArgs);
    }

    /**
     * @template TType
     *
     * @return self<int,TType>
     */
    public static function fill(int $count, mixed $value): self
    {
        return new self(array_fill(0, $count, $value));
    }

    /**
     * @return self<int,int>
     */
    public static function range(int $end, int $start = 1, int $step = 1): self
    {
        return self::of(range($start, $end, $step));
    }

    /**
     * @template TType
     * @template TTypeKey
     *
     * @param array<TTypeKey,TType>
     *
     * @return self<int,TType>
     */
    public static function fromValues(array $assoc): self
    {
        return Bunch::of(array_values($assoc));
    }

    /**
     * @template TType
     * @template TTypeKey
     *
     * @param array<TTypeKey,TType>
     *
     * @return self<int,TTypeKey>
     */
    public static function fromKeys(array $assoc): self
    {
        return Bunch::of(array_keys($assoc));
    }

    /**
     * @template TArrayKey
     * @template TArrayValue
     *
     * @param array<TArrayKey,TArrayValue> $assoc
     *
     * @return Bunch<int,array{TArrayKey,TArrayValue}>
     */
    public static function unzip(array $assoc): self
    {
        $data = [];
        $keys = array_keys($assoc);
        $values = array_values($assoc);
        $count = count($keys);

        for ($i = 0; $i < $count; ++$i) {
            $data[] = [$keys[$i], $values[$i]];
        }

        return self::of($data);
    }

    /**
     * @template TReturnKey
     * @template TReturnValue
     *
     * @param null|\Closure(TValue):array<TReturnKey|TReturnValue> $mapFunction
     */
    public function zip(?callable $mapFunction = null): array
    {
        $clone = Bunch::of($this);
        if ($mapFunction) {
            $clone->map($mapFunction);
        }

        return array_combine(
            Bunch::of($clone)->map(fn ($x) => $x[0])->toArray(),
            Bunch::of($clone)->map(fn ($x) => $x[1])->toArray(),
        );
    }

    /**
     * @return self<int,<string></string>
     */
    public static function fromExplode(string $delimiter, string $string): self
    {
        return self::of(explode($delimiter, $string));
    }

    /**
     * @return array<TKey,TValue>
     */
    public function get(): array
    {
        return $this->data;
    }

    /**
     * @return TValue
     */
    public function at(mixed $keyOrIndex): mixed 
    {
        return $this->data[$keyOrIndex];
    }

    /**
     * Alias of `get()`.
     *
     * @return array<TKey,TValue>
     */
    public function toArray(): array
    {
        return $this->get();
    }

    /**
     * @return self<int>
     */
    public function asIntegers(): self
    {
        return $this->filter(fn ($x) => is_numeric($x))->map(fn ($x) => (int) $x);
    }

    /**
     * @return self<float>
     */
    public function asFloats(): self
    {
        return $this->filter(fn ($x) => is_numeric($x))->map(fn ($x) => (float) $x);
    }

    /**
     * @template TDefault
     *
     * @param TDefault $default Default value used when no value is present
     *
     * @return TDefault|TValue
     */
    public function min(mixed $default = null): mixed
    {
        return $this->count()
            ? min(...$this->data)
            : $default;
    }

    /**
     * @template TDefault
     *
     * @param TDefault $default Default value used when no value is present
     *
     * @return TDefault|TValue
     */
    public function max(mixed $default = null): mixed
    {
        return $this->count()
            ? max(...$this->data)
            : $default;
    }

    /**
     * @template TDefault
     *
     * @param TDefault $default Default value used when no value is present
     *
     * @return TDefault|TValue
     */
    public function average(mixed $default = null): mixed
    {
        $count = $this->count();
        if (!$count) {
            return $default;
        }

        /** @var array<null|array|bool|float|int|string> $data */
        $data = $this->data;
        $sum = $data[0];

        for ($i = 1; $i < $count; ++$i) {
            $sum += $data[$i];
        }

        return $sum / $count;
    }

    /**
     * @param \Closure(TValue):bool $callback
     * @return static
     */
    public function filter(?callable $callback = null): self
    {
        return $this->withNewData(array_filter($this->data, $callback));
    }

    /**
     * @template X
     *
     * @param string|X $class
     *
     * @return self<int,X>
     */
    public function onlyInstancesOf(string $class)
    {
        return $this->filter(fn ($x) => $x instanceof $class);
    }

    public function partitionFilter(callable $callback): array
    {
        $output = [];
        foreach ($this->data as $element) {
            $result = (int) $callback($element);
            $output[$result] ??= [];
            $output[$result][] = $element;
        }

        return $output;
    }

    /**
     * @template TReturnValue
     *
     * @param \Closure(TValue):TReturnValue $callback
     *
     * @return Bunch<TKey,TReturnValue>
     */
    public function map(callable $callback): self
    {
        return $this->withNewData(array_map($callback, $this->data));
    }

    public function instanciates(array $args=[])
    {
        return $this->map(fn($class) => Injector::instanciate($class, $args));
    }

    public function diff(array|Bunch $values): self
    {
        if ($values instanceof Bunch) {
            $values = $values->get();
        }

        return $this->withNewData(array_diff($this->data, $values));
    }

    /**
     * @return Bunch<TKey,mixed>
     */
    public function key(array|string $keys, string $compoundKeySeparator = '.'): self
    {
        if (!$this->count()) {
            return $this;
        }

        $keys = Utils::toArray($keys);

        $arrayMode = is_array($this->data[0] ?? false);
        $valueGetter = $arrayMode
            ? fn ($object, $key) => $object[$key] ?? new NoValue()
            : fn ($object, $key) => $object->{$key} ?? new NoValue();

        return $this->map(function ($element) use (&$keys, $valueGetter, $compoundKeySeparator) {
            foreach ($keys as $key) {
                $loopValue = $this->getValueFromCompoundKey($element, $key, $valueGetter, $compoundKeySeparator);
                if (!$loopValue instanceof NoValue) {
                    return $loopValue;
                }
            }

            throw new \InvalidArgumentException('No value found in object for keys '.print_r($keys, true));
        });
    }

    public function flat(): self
    {
        $data = [];
        foreach ($this->data as $array) {
            array_push($data, ...$array);
        }

        return $this->withNewData($data);
    }

    /**
     * @template TMergedKey
     * @template TMergedValue
     *
     * @param array<TMergedKey,TMergedValue>|Bunch<TMergedKey,TMergedValue> $value
     *
     * @return Bunch<TKey|TMergedKey,TMergedValue|TValue>
     */
    public function merge(array|Bunch $value): self
    {
        return $this->withNewData(
            array_merge($this->data, Bunch::of($value)->get())
        );
    }

    public function sort(callable|int $callbackOrSortMode = SORT_REGULAR): self
    {
        is_callable($callbackOrSortMode)
            ? usort($this->data, fn ($a, $b) => $callbackOrSortMode($a) < $callbackOrSortMode($b) ? -1 : 1)
            : sort($this->data, $callbackOrSortMode);

        return $this->withNewData($this->data);
    }

    /**
     * @param \Closure(TValue) $callback
     */
    public function forEach(callable $callback): self
    {
        array_walk($this->data, $callback);

        return $this;
    }

    /**
     * @param \Closure(TValue) $callback
     */
    public function any(callable $callback): bool
    {
        foreach ($this->data as $element) {
            if (true === $callback($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Closure(TValue) $callback
     */
    public function all(callable $callback): bool
    {
        foreach ($this->data as $element) {
            if (false === $callback($element)) {
                return false;
            }
        }

        return true;
    }

    public function uniques(): self
    {
        return $this->withNewData(array_unique($this->data));
    }

    public function push(mixed ...$element): self
    {
        array_push($this->data, ...$element);

        return $this;
    }

    public function unshift(mixed ...$element): self
    {
        array_unshift($this->data, ...$element);

        return $this;
    }

    public function pop(int $count = 1): self
    {
        for ($i = 0; ($i < $count) && count($this->data); ++$i) {
            array_pop($this->data);
        }

        return $this;
    }

    public function shift(int $count = 1): self
    {
        for ($i = 0; ($i < $count) && count($this->data); ++$i) {
            array_shift($this->data);
        }

        return $this;
    }

    public function join(string $glue = ','): string
    {
        return join($glue, $this->data);
    }

    /**
     * @param \Closure(TValue) $callback
     * @return ?TValue
     */
    public function first(?callable $callback=null): mixed
    {
        if ($callback === null) {
            return $this->data[0] ?? null;
        }

        foreach ($this->data as $element) {
            if (true === $callback($element)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @param \Closure(TValue):bool $callback
     */
    public function firstIndex(callable $callback): int
    {
        $dataCount = count($this->data);
        for ($i = 0; $i < $dataCount; ++$i) {
            $element = $this->data[$i];
            if (true === $callback($element)) {
                return $i;
            }
        }

        return -1;
    }


    /**
     * @param \Closure(TValue) $callback
     * @return ?TValue
     */
    public function last(?callable $callback=null): mixed
    {
        if ($callback === null && count($this->data)) {
            return array_last($this->data);
        }

        $data = array_reverse($this->data);
        foreach ($data as $element) {
            if (true === $callback($element)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @param \Closure(TValue):bool $callback
     */
    public function lastIndex(callable $callback): int
    {
        $dataCount = count($this->data);
        for ($i = $dataCount-1; $i >= 0; --$i) {
            $element = $this->data[$i];
            if (true === $callback($element)) {
                return $i;
            }
        }

        return -1;
    }

    public function has(mixed $value): bool
    {
        return in_array($value, $this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function shuffle(): self
    {
        shuffle($this->data);

        return $this;
    }

    /**
     * @template TAcc
     *
     * @param \Closure(TAcc,TValue):TAcc $callback
     * @param TAcc                       $start
     */
    public function reduce(callable $callback, mixed $start = 0): mixed
    {
        $acc = $start;
        foreach ($this->data as $element) {
            $acc = $callback($acc, $element);
        }

        return $acc;
    }

    /**
     * @template TReturn
     *
     * @param \Closure(TValue):TReturn|string|null $keyOrCallback
     * @param TReturn|TValue                       $start
     */
    public function sum(string|Closure|null $keyOrCallback=null): mixed
    {
        if (is_string($keyOrCallback))
            return $this->key($keyOrCallback)->sum();

        if (is_callable($keyOrCallback))
            return $this->reduce(fn($acc, $cur) => $acc + $keyOrCallback($cur), 0);

        return $this->reduce(function($acc, $x){
            /** @var mixed $x */
            return $acc + $x;
        }, 0);
    }

    /**
     * @template TNKey
     * @template TNValues
     * 
     * @param array<TNKey,TNValues> $data
     * @return self<int,TNValues>
     */
    protected function withNewData(array $data): self
    {
        $this->data = array_values($data);

        return $this;
    }

    protected function getValueFromCompoundKey($object, string $compoundKey, callable $valueGetter, string $compoundKeySeparator = '.')
    {
        if (!str_contains($compoundKey, $compoundKeySeparator)) {
            return ($valueGetter)($object, $compoundKey);
        }

        list($key, $rest) = explode($compoundKeySeparator, $compoundKey, 2);
        $subValue = ($valueGetter)($object, $key);

        if ($subValue instanceof NoValue) {
            return $subValue;
        }

        return $this->getValueFromCompoundKey($subValue, $rest, $valueGetter, $compoundKeySeparator);
    }

    /**
     * @return self<int,mixed>
     */
    public static function fromQuery(string $query, array $context=[], ?Database $database=null, ?string $key=null): self
    {
        $database ??= Database::getInstance();

        $data = static::of($database->query($query, $context, \PDO::FETCH_ASSOC));

        if (!count($data))
            return $data;

        if ($key)
            return $data->key($key);

        return $data;
    }
}
