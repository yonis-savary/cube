<?php

namespace YonisSavary\Cube\Data;

use YonisSavary\Cube\Utils\Utils;

class Bunch
{
    protected array $data;

    public static function of(mixed $element): self
    {
        if ($element instanceof Bunch)
            $element = $element->get();

        if (!(is_array($element) && Utils::isList($element)))
            $element = [$element];

        return new self($element);
    }

    public static function fill(int $count, mixed $value): self
    {
        return new self(array_fill(0, $count, $value));
    }

    public static function range(int $end, int $start=1, int $step=1): self
    {
        return self::of(range($start, $end, $step));
    }

    public static function fromValues(array $assoc): self
    {
        return Bunch::of(array_values($assoc));
    }

    public static function fromKeys(array $assoc): self
    {
        return Bunch::of(array_keys($assoc));
    }

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

    public function __construct(array $initialData=[])
    {
        $this->data = $initialData;
    }

    public function __clone()
    {
        return new self($this->get());
    }

    public function get(): array
    {
        return $this->data;
    }

    /**
     * Alias of `get()`
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

    public function asIntegers(): self
    {
        return $this->filter(fn($x) => is_numeric($x))->map(fn($x) => (int) $x);
    }

    public function asFloats(): self
    {
        return $this->filter(fn($x) => is_numeric($x))->map(fn($x) => (float) $x);
    }

    public function filter(?callable $callback=null): self
    {
        return $this->withNewData(array_filter($this->data, $callback));
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

    public function map(callable $callback): self
    {
        return $this->withNewData(array_map($callback, $this->data));
    }

    public function merge(array|Bunch $value): self
    {
        return $this->withNewData(
            array_merge($this->data, Bunch::of($value)->get())
        );
    }

    public function sort(callable|int $callbackOrSortMode=SORT_REGULAR): self
    {
        is_callable($callbackOrSortMode) ?
            usort($this->data, fn($a, $b) => $callbackOrSortMode($a) < $callbackOrSortMode($b) ? -1 : 1):
            sort($this->data, $callbackOrSortMode);
        return $this->withNewData($this->data);
    }

    public function forEach(callable $callback): void
    {
        array_walk($this->data, $callback);
    }

    public function any(callable $callback): bool
    {
        foreach ($this->data as $element)
        {
            if ($callback($element) === true)
                return true;
        }
        return false;
    }

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

    public function first(callable $callback): mixed
    {
        foreach ($this->data as $element)
        {
            if ($callback($element) === true)
                return $element;
        }
        return null;
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

    public function reduce(callable $callback, mixed $start=0): mixed
    {
        $acc = $start;
        foreach ($this->data as $element)
            $acc = $callback($acc, $element);

        return $acc;
    }
}