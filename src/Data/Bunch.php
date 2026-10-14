<?php

namespace Cube\Data;

use ArrayIterator;
use Closure;
use Countable;
use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Data\Classes\NoValue;
use Cube\Data\Database\Database;
use Cube\Utils\Utils;
use IteratorAggregate;
use Traversable;

/**
 * @template TKey
 * @template TValue
 * @template TCallback of \Closure(TValue)|string
 * @template TNullableCallback of ?TCallback
 * @template TCallbacks of TCallback|TCallback[]
 * @template TNullableCallbacks of TNullableCallback|TNullableCallback[]
 *
 * @implements IteratorAggregate<TKey,TValue>
 */
class Bunch implements Countable, IteratorAggregate
{
    /** @var array<TKey,TValue> */
    protected array $data;

    /**
     * @template TCKey
     * @template TCValue
     *
     * @param array<TCKey,TCValue> $initialData
     *
     * @return self<TCKey,TCValue>
     */
    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }


    /**
     * @template TElement
     *
     * @param TElement $element
     *
     * @return (TElement is Bunch ? TElement : (TElement is list ? self<int,value-of<TElement>> : self<int,TElement>))
     */
    public static function wrap(mixed $element): self
    {
        if ($element instanceof Bunch) {
            return new static($element->get());
        }

        if (!(is_array($element) && Utils::isList($element))) {
            $element = [$element];
        }

        return new static($element);
    }


    /**
     * Loads a Bunch of non-abstract classes that extends a given parent Class (Instanciates classes).
     * To perform this action without instanciating, call `Bunch::wrap(Autoloader::classesThatExtends($class))`
     *
     * @template TClassname
     * @param class-string<TClassname> $class
     * @return self<int,TClassname>
     */
    public static function fromExtends(string $class, array $constructorArgs=[]): self
    {
        return Bunch::wrap(Autoloader::classesThatExtends($class))->instanciates($constructorArgs);
    }

    /**
     * @template TClassname
     * @param class-string<TClassname> $implements
     * @return self<int,TClassname>
     */
    public static function fromImplements(string $implements, array $constructorArgs=[]): self
    {
        return Bunch::wrap(Autoloader::classesThatImplements($implements))->instanciates($constructorArgs);
    }

    /**
     * @template TClassname
     * @param class-string<TClassname> $uses
     * @return self<int,TClassname>
     */
    public static function fromUses(string $uses, array $constructorArgs=[]): self
    {
        return Bunch::wrap(Autoloader::classesThatUses($uses))->instanciates($constructorArgs);
    }

    /**
     * @template TType
     *
     * @return self<int,TType>
     */
    public static function fill(int $count, mixed $value): self
    {
        return new static(array_fill(0, $count, $value));
    }

    /**
     * @template TAssocKey of array-key
     * @template TAssocValue
     *
     * @param array<TAssocKey,TAssocValue> $assoc
     *
     * @return self<TAssocKey,TAssocValue>
     */
    public static function fromAssoc(array $assoc): self
    {
        return new static($assoc);
    }

    /**
     * @return self<int,int>
     */
    public static function range(int $length): self
    {
        if ($length <= 0)
            return new static();

        return self::rangeFrom(0, $length - 1);
    }

    /**
     * @return self<int,int>
     */
    public static function rangeFrom(int $start, int $end, int $step = 1): self
    {
        return new static(range($start, $end, $step));
    }

    /**
     * @template TType
     * @template TTypeKey
     *
     * @param array<TTypeKey,TType> $assoc
     *
     * @return self<int,TType>
     */
    public static function fromValues(array $assoc): self
    {
        return Bunch::wrap(array_values($assoc));
    }

    /**
     * @template TType
     * @template TTypeKey
     *
     * @param array<TTypeKey,TType> $assoc
     *
     * @return self<int,TTypeKey>
     */
    public static function fromKeys(array $assoc): self
    {
        return Bunch::wrap(array_keys($assoc));
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

        return self::wrap($data);
    }

    /** @param TNullableCallbacks $callbacks */
    protected function normalizeCallbacks(array|callable|string|null $callbacks)
    {
        if (is_callable($callbacks) || is_string($callbacks))
            $callbacks = [$callbacks];

        foreach ($callbacks as &$callback)
            $callback = $this->normalizeSingleCallback($callback);

        return $callbacks;
    }

    /** @param TNullableCallback $callback */
    protected function normalizeSingleCallback(callable|string|null $callback) {
        if ($callback === null)
            return null;

        if (is_string($callback))
            return fn($el) => is_object($el) ? $el->$callback : $el[$callback];

        return $callback;
    }

    /**
     * @template TReturnKey
     * @template TReturnValue
     *
     * @param null|\Closure(TValue):array<TReturnKey|TReturnValue> $mapFunction
     */
    public function zip(?callable $mapFunction = null): array
    {
        $clone = Bunch::wrap($this);
        if ($mapFunction) {
            $clone = $clone->map($mapFunction);
        }

        return array_combine(
            Bunch::wrap($clone)->map(fn ($x) => $x[0])->toArray(),
            Bunch::wrap($clone)->map(fn ($x) => $x[1])->toArray(),
        );
    }

    /**
     * @return self<int,string>
     */
    public static function fromExplode(string $delimiter, string $string): self
    {
        return self::wrap(explode($delimiter, $string));
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

    /** @return self<int> */
    public function asIntegers(bool $filterNumerics = true): self
    {
        return $this
            ->when($filterNumerics, fn($b) => $b->filter(is_numeric(...)))
            ->map(fn ($x) => (int) $x);
    }

    /** @return self<float> */
    public function asFloats(bool $filterNumerics = true): self
    {
        return $this
            ->when($filterNumerics, fn($b) => $b->filter(is_numeric(...)))
            ->map(fn ($x) => (float) $x);
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
            ? min($this->data)
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
            ? max($this->data)
            : $default;
    }

    /**
     * @template TDefault
     *
     * @param TNullableCallback $callback
     * @param TDefault $default Default value used when no value is present
     *
     * @return TDefault|TValue
     */
    public function average(callable|string|null $callback=null, mixed $default = null): mixed
    {
        $count = $this->count();

        return $count
            ? $this->sum($callback) / $count
            : $default;
    }

    /** @param TNullableCallback $callback */
    public function filter(callable|string|null $callback = null, bool $keepValues = false): static
    {
        return $this->withNewData(array_filter(
            $this->data,
            $this->normalizeSingleCallback($callback)
        ))
        ->when($keepValues, fn($bunch) => $bunch->values());
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
        $output = [0 => [], 1 => []];
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
     * @param TCallback $callback
     * @return Bunch<TKey,TReturnValue>
     */
    public function map(callable|string $callback): self
    {
        $callback = $this->normalizeSingleCallback($callback);
        return $this->withNewData(array_map($callback, $this->data));
    }

    public function flatMap(callable|string $callback): self
    {
        return $this->map($callback)->flat();
    }

    public function instanciates(array $args=[])
    {
        return $this->map(fn($class) => Injector::getInstance()->instanciate($class, $args));
    }

    public function diff(array|Bunch $values): self
    {
        if ($values instanceof Bunch) {
            $values = $values->get();
        }

        return $this->withNewData(array_diff($this->data, $values));
    }

    /**
     * @param \Closure(Bunch<TKey,TValue>):Bunch<TKey,TValue> $callback
     */
    public function when(bool $condition, callable $callback) {
        return $condition ? $callback($this): $this;
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

        $arrayMode = is_array($this->first() ?? false);
        $valueGetter = $arrayMode
            ? fn ($object, $key) => array_key_exists($key, $object) ? $object[$key] : new NoValue()
            : fn ($object, $key) => property_exists($object, $key) ? $object->{$key} : ($object->{$key} ?? new NoValue());

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

    /**
     * @param TCallbacks $callbacks
     * @return array<mixed>
     */
    public function groupBy(callable|array|string $callbacks): array
    {
        $callbacks = $this->normalizeCallbacks($callbacks);

        $mainCallback = array_shift($callbacks);

        $grouped = [];

        foreach ($this->data as $row) {
            $groupId = $mainCallback($row);
            $grouped[$groupId] ??= [];
            $grouped[$groupId][] = $row;
        }

        if (count($callbacks)) {
            foreach ($grouped as &$group) {
                $group = Bunch::wrap($group)->groupBy($callbacks);
            }
        }

        return $grouped;
    }

    public function flat(): self
    {
        $data = [];
        foreach ($this->data as $array) {
            if ($array instanceof Bunch)
                $array = $array->toArray();

            array_push($data, ...array_values($array));
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
            array_merge($this->data, Bunch::wrap($value)->get())
        );
    }

    /**
     * @param TCallback|TCallback[]|int $callbackOrSortMode
     */
    public function sort(callable|int|array $callbackOrSortMode = SORT_REGULAR): self
    {
        $sorted = $this->data;

        if (is_int($callbackOrSortMode)) {
            sort($sorted, $callbackOrSortMode);
            return $this->withNewData($sorted);
        }

        $callbacks = $this->normalizeCallbacks($callbackOrSortMode);

        usort($sorted, function ($a, $b) use ($callbacks) {
            foreach ($callbacks as $callback)
                if ($order = $callback($a) <=> $callback($b))
                    return $order;

            return 0;
        });

        return $this->withNewData($sorted);
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
            if (true == $callback($element)) {
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
            if (false == $callback($element)) {
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
            return count($this->data)
                ? $this->data[array_key_first($this->data)]
                : null;
        }

        foreach ($this->data as $element) {
            if ($callback($element)) {
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
        $data = array_values($this->data);
        $dataCount = count($data);
        for ($i = 0; $i < $dataCount; ++$i) {
            $element = $data[$i];
            if ($callback($element)) {
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
        if ($callback === null) {
            return count($this->data)
                ? $this->data[array_key_last($this->data)]
                : null;
        }

        $data = array_reverse($this->data);
        foreach ($data as $element) {
            if ($callback($element)) {
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
        $data = array_values($this->data);
        $dataCount = count($data);
        for ($i = $dataCount-1; $i >= 0; --$i) {
            $element = $data[$i];
            if ($callback($element)) {
                return $i;
            }
        }

        return -1;
    }

    public function has(mixed $value): bool
    {
        return $this->any(fn($el) => $el === $value);
    }

    /**
     * @return Traversable<TKey,TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
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
     * @param \Closure(TValue):TReturn|string|null $callback
     * @return TReturn|TValue
     */
    public function sum(string|callable|null $callback=null): mixed
    {
        $callback =
            $this->normalizeSingleCallback($callback) ??
            fn($el) => $el;

        return $this->reduce(fn($acc, $cur) => $acc + $callback($cur), 0);
    }

    /**
     * @param \Closure(TValue[],Bunch<int,TValue>):void $callback
     */
    public function chunk(int $chunkSize, callable $callback): void
    {
        $data = &$this->data;
        $size = count($data);
        $chunkCount = ceil($size / $chunkSize);

        for ($i=0; $i<$chunkCount; $i++) {
            $chunkData = array_slice($data, $i * $chunkSize, $chunkSize);
            $callback($chunkData, Bunch::wrap($chunkData));
        }
    }

    /** @return self<int,TValue> */
    public function values(): self
    {
        return $this->withNewData(array_values($this->data));
    }

    public function slice(int $offset, ?int $length = null, bool $preserveKey = false)
    {
        return $this->withNewData(array_slice($this->data, $offset, $length, $preserveKey));
    }

    /**
     * @template TNKey
     * @template TNValues
     *
     * @param array<TNKey,TNValues> $data
     * @return self<TNKey,TNValues>
     */
    protected function withNewData(array $data): self
    {
        return new static($data);
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

        $data = static::wrap($database->query($query, $context, \PDO::FETCH_ASSOC));

        if (!count($data))
            return $data;

        if ($key)
            return $data->key($key);

        return $data;
    }
}
