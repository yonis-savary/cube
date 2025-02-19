<?php

namespace Cube\Data;

use Cube\Utils\Utils;

/**
 * @template TKey
 * @template TValue
 */
class Bunch
{
    /** @var array<TKey,TValue> */
    protected array $data;

    /**
     * @template TType
     * @template TTypeKey
     * @param array<TType>|TType|Bunch<TTypeKey,TType> $element
     * @return self<int,TType>
     */
    public static function of(mixed $element): self
    {
        if ($element instanceof Bunch)
            $element = $element->get();

        if (!(is_array($element) && Utils::isList($element)))
            $element = [$element];

        return new self($element);
    }

    /**
     * @template TType
     * @return self<int,TType>
     */
    public static function fill(int $count, mixed $value): self
    {
        return new self(array_fill(0, $count, $value));
    }

    /**
     * @return self<int,int>
     */
    public static function range(int $end, int $start=1, int $step=1): self
    {
        return self::of(range($start, $end, $step));
    }

    /**
     * @template TType
     * @template TTypeKey
     * @param array<TTypeKey,TType>
     * @return self<int,TType>
     */
    public static function fromValues(array $assoc): self
    {
        return Bunch::of(array_values($assoc));
    }

    /**
     * @template TType
     * @template TTypeKey
     * @param array<TTypeKey,TType>
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
     * @return Bunch<int,array{TArrayKey,TArrayValue}>
     */
    public static function unzip(array $assoc): self
    {
        $data = [];
        $keys = array_keys($assoc);
        $values = array_values($assoc);
        $count = count($keys);

        for ($i=0; $i<$count; $i++)
            $data[] = [$keys[$i], $values[$i]];

        return self::of($data);
    }

    /**
     * @template TReturnKey
     * @template TReturnValue
     * @param null|\Closure(TValue):array<TReturnKey|TReturnValue> $mapFunction
     */
    public function zip(?callable $mapFunction=null): array
    {
        $clone = Bunch::of($this);
        if ($mapFunction)
            $clone->map($mapFunction);

        return array_combine(
            Bunch::of($clone)->map(fn($x) => $x[0])->toArray(),
            Bunch::of($clone)->map(fn($x) => $x[1])->toArray(),
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
     * @template TType
     * @param array<TType> $initialData
     * @return self<int,TType>
     */
    public function __construct(array $initialData=[])
    {
        $this->data = $initialData;
    }

    public function __clone()
    {
        return new self($this->get());
    }

    /**
     * @return array<TKey,TValue>
     */
    public function get(): array
    {
        return $this->data;
    }

    /**
     * Alias of `get()`
     * @return array<TKey,TValue>
     */
    public function toArray(): array
    {
        return $this->get();
    }

    protected function withNewData(array $data): self
    {
        $this->data = array_values($data);
        return $this;
    }

    /**
     * @return self<int>
     */
    public function asIntegers(): self
    {
        return $this->filter(fn($x) => is_numeric($x))->map(fn($x) => (int) $x);
    }

    /**
     * @return self<float>
     */
    public function asFloats(): self
    {
        return $this->filter(fn($x) => is_numeric($x))->map(fn($x) => (float) $x);
    }

    /**
     * @return static
     */
    public function filter(?callable $callback=null): self
    {
        return $this->withNewData(array_filter($this->data, $callback));
    }

    /**
     * @template X
     * @param string|X $class
     * @return self<X>
     */
    public function onlyInstancesOf(string $class): self
    {
        return $this->filter(fn($x) => $x instanceof $class);
    }

    public function partitionFilter(callable $callback): array
    {
        $output = [];
        foreach ($this->data as $element)
        {
            $result = (int) $callback($element);
            $output[$result] ??= [];
            $output[$result][] = $element;
        }

        return $output;
    }

    /**
     * @template TReturnValue
     * @param \Closure(TValue):TReturnValue $callback
     * @return Bunch<TKey,TReturnValue>
     */
    public function map(callable $callback): self
    {
        return $this->withNewData(array_map($callback, $this->data));
    }

    public function flat(): self
    {
        $data = [];
        foreach ($this->data as $array)
            array_push($data, ...$array);

        return $this->withNewData($data);
    }

    /**
     * @template TMergedKey
     * @template TMergedValue
     * @param array<TMergedKey,TMergedValue>|Bunch<TMergedKey,TMergedValue> $value
     * @return Bunch<TKey|TMergedKey,TValue|TMergedValue>
     */
    public function merge(array|Bunch $value): self
    {
        return $this->withNewData(
            array_merge($this->data, Bunch::of($value)->get())
        );
    }

    /**
     * @param \Closure(TValue) $callback
     */
    public function sort(callable|int $callbackOrSortMode=SORT_REGULAR): self
    {
        is_callable($callbackOrSortMode) ?
            usort($this->data, fn($a, $b) => $callbackOrSortMode($a) < $callbackOrSortMode($b) ? -1 : 1):
            sort($this->data, $callbackOrSortMode);
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
        foreach ($this->data as $element)
        {
            if ($callback($element) === true)
                return true;
        }
        return false;
    }

    /**
     * @param \Closure(TValue) $callback
     */
    public function all(callable $callback): bool
    {
        foreach ($this->data as $element)
        {
            if ($callback($element) === false)
                return false;
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

    public function pop(int $count=1): self
    {
        for ($i=0; ($i<$count) && count($this->data); $i++)
            array_pop($this->data);
        return $this;
    }

    public function shift(int $count=1): self
    {
        for ($i=0; ($i<$count) && count($this->data); $i++)
            array_shift($this->data);
        return $this;
    }

    public function join(string $glue=","): string
    {
        return join($glue, $this->data);
    }

    /**
     * @param \Closure(TValue) $callback
     */
    public function first(callable $callback): mixed
    {
        foreach ($this->data as $element)
        {
            if ($callback($element) === true)
                return $element;
        }
        return null;
    }

    /**
     * @param \Closure(TValue):bool $callback
     */
    public function firstIndex(callable $callback): int
    {
        $dataCount = count($this->data);
        for ($i=0; $i<$dataCount; $i++)
        {
            $element = $this->data[$i];
            if ($callback($element) === true)
                return $i;
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
     * @param \Closure(TAcc,TValue):TAcc $callback
     * @param TAcc $start
     */
    public function reduce(callable $callback, mixed $start=0): mixed
    {
        $acc = $start;
        foreach ($this->data as $element)
            $acc = $callback($acc, $element);

        return $acc;
    }
}