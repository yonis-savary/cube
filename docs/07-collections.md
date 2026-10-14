<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./06-components.md">Previous : Components</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./08-events.md">Next : Events</a></div></td></tr></table>

# Collections

`Cube\Data\Bunch` is the collection Cube uses everywhere : a wrapper around a PHP array with a fluent
set of methods, so a transformation reads as one chain instead of nested `array_*` calls.

```php
$names = Bunch::wrap($products)
    ->filter(fn (Product $product) => $product->price_dollar > 10)
    ->sort(fn (Product $product) => $product->name)
    ->map(fn (Product $product) => $product->name)
    ->join(', ');
```

Queries give one directly : `Product::select()->toBunch()`, and a route callback can return a
`Bunch` of models, converted to a JSON array (see
[Controllers and responses](./13-controllers-and-responses.md)).

## Creating a bunch

| Constructor | Gives |
|---|---|
| `Bunch::wrap($value)` | a bunch of the values of a **list**, a copy of another `Bunch`, or a bunch holding `$value` alone |
| `Bunch::fromAssoc($assoc)` | a bunch of `$assoc` as it is, keys included |
| `Bunch::fromValues($assoc)` | the values of an associative array |
| `Bunch::fromKeys($assoc)` | the keys of an associative array |
| `Bunch::unzip($assoc)` | one `[key, value]` pair per entry |
| `Bunch::fromExplode($delimiter, $string)` | the parts of `$string` |
| `Bunch::range($length)` | the integers from `0` to `$length - 1` |
| `Bunch::rangeFrom($start, $end, $step = 1)` | the integers from `$start` to `$end`, both included |
| `Bunch::fill($count, $value)` | `$value` repeated `$count` times |
| `Bunch::fromQuery($sql, $context = [], $database = null, $key = null)` | the rows of a raw SQL query, as associative arrays ; with `$key`, the value of that column only |
| `Bunch::fromExtends($class)`, `fromImplements($interface)`, `fromUses($trait)` | an instance of every class of that kind, see [Applications and discovery](./04-applications-and-discovery.md) |

`Bunch::wrap()` treats an associative array as **one** value

```php
Bunch::wrap([1, 2, 3])->count();                 // 3
Bunch::wrap(['name' => 'screen'])->count();      // 1 : the array itself is the only element
Bunch::fromValues(['name' => 'screen'])->get(); // ['screen']
Bunch::fromAssoc(['name' => 'screen']);         // keeps the key
```

## Transforming

Each of these returns a **new** bunch and leaves the original untouched

| Method | Does |
|---|---|
| `map($callback)` | applies `$callback` to every value, keys kept |
| `flatMap($callback)` | `map()` then `flat()` |
| `flat()` | merges a bunch of arrays (or of bunches) into one list, one level deep |
| `filter($callback = null, $keepValues = false)` | keeps the values for which `$callback` is truthy ; without a callback, drops the falsy values. Keys are kept unless `$keepValues` is `true` |
| `onlyInstancesOf($class)` | keeps the objects of that class |
| `sort($callbackOrMode = SORT_REGULAR)` | sorts by a PHP sort flag, by the value a callback returns, or by several callbacks in turn ; keys are reset |
| `uniques()` | drops duplicates |
| `diff($values)` | drops the values present in `$values` |
| `merge($values)` | appends an array or a bunch |
| `slice($offset, $length = null, $preserveKey = false)` | a part of the bunch |
| `values()` | resets the keys |
| `key($keys)` | the value of a property or array key of each element (see below) |
| `asIntegers($filterNumerics = true)`, `asFloats(...)` | casts every value, dropping non-numeric ones first by default |
| `when($condition, $callback)` | applies `$callback` to the bunch only when `$condition` is `true` |

Wherever a method takes a callback for a value, a string works too : it reads that property (on an
object) or that key (on an array)

```php
$products->map('name');                  // same as ->map(fn ($product) => $product->name)
$products->sort(['category', 'price']);  // by category, then by price
$orders->sum('total');
```

`key()` goes further : it accepts several candidate keys, the first one present wins, and a
dotted path reaches into nested values. It throws an `InvalidArgumentException` when no key holds a
value

```php
$rows->key('customer.email');            // $row['customer']['email'] for each row
$rows->key(['nickname', 'name']);        // the nickname when there is one, else the name
```

## Reading values

| Method | Returns |
|---|---|
| `get()`, `toArray()` | the underlying array |
| `count()` | the number of values (`Bunch` is `Countable`, so `count($bunch)` works too) |
| `at($key)` | the value at that key or index |
| `first($callback = null)`, `last($callback = null)` | the first / last value, or the first / last one matching ; `null` when none |
| `firstIndex($callback)`, `lastIndex($callback)` | the position of the first / last match, `-1` when none |
| `has($value)` | whether `$value` is present (strict comparison) |
| `any($callback)`, `all($callback)` | whether one / every value matches |
| `join($glue = ',')` | the values joined in a string |

A `Bunch` is iterable : `foreach ($bunch as $key => $value)` walks its values with their keys.

## Aggregating

| Method | Returns |
|---|---|
| `reduce($callback, $start = 0)` | the accumulated value, `$callback($accumulator, $value)` |
| `sum($callback = null)` | the sum of the values, or of what `$callback` returns |
| `average($callback = null, $default = null)` | the mean, `$default` for an empty bunch |
| `min($default = null)`, `max($default = null)` | the smallest / largest value, `$default` for an empty bunch |

```php
$revenue = Order::select()->toBunch()->sum(fn (Order $order) => $order->quantity * $order->unit_price);
```

## Grouping and splitting

These return plain arrays

| Method | Returns |
|---|---|
| `groupBy($callbacks)` | the values grouped under the key `$callback` returns ; with a list of callbacks, nested groups |
| `zip($callback = null)` | an associative array built from the `[key, value]` pairs the values are (or `$callback` returns) |
| `partitionFilter($callback)` | `[0 => rejected values, 1 => kept values]` |

```php
$byCategory = $products->groupBy('category');                  // ['screens' => [...], 'cables' => [...]]
$byCategoryAndBrand = $products->groupBy(['category', 'brand']); // ['screens' => ['acme' => [...]], ...]

$pricesByName = $products->zip(fn (Product $product) => [$product->name, $product->price_dollar]);

[$outOfStock, $inStock] = $products->partitionFilter(fn (Product $product) => $product->stock > 0);
```

`partitionFilter()` groups the values by the integer the callback returns (`false` is `0`, `true` is
`1`). The `0` and `1` groups are always there, empty when nothing fell in them.

`chunk($size, $callback)` walks the bunch by slices : `$callback` receives each slice as an array and
as a bunch

```php
$products->chunk(100, fn (array $slice, Bunch $bunch) => $indexer->index($slice));
```

## Mutating in place

A few methods change the bunch itself and return it, rather than a copy

| Method | Does |
|---|---|
| `push(...$values)`, `unshift(...$values)` | adds values at the end / start |
| `pop($count = 1)`, `shift($count = 1)` | removes values from the end / start |
| `shuffle()` | shuffles the values |
| `forEach($callback)` | calls `$callback` on every value, for its effects |

Use `map()` to build new values ; `forEach()` is for side effects.

## Typing a bunch

`Bunch` is generic over its keys and values in PHPDoc, so annotate what you return and your IDE
knows what comes out of `first()` or `map()`

```php
/**
 * @return Bunch<int,Product>
 */
public function inStock(): Bunch
{
    return Product::select()->where('stock', 0, '>')->toBunch();
}
```

The transforming methods build their result with `new static`, so a subclass of `Bunch` stays that
subclass through a chain.

`Cube\Data\Classes\ArrayOf` and `BunchOf` are parameter attributes for typed lists inside a data transfer
object, see [Data transfer objects](./20-data-transfer-objects.md).

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./06-components.md">Previous : Components</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./08-events.md">Next : Events</a></div></td></tr></table>
