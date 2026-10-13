<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./19-model-apis.md">Previous : Model apis</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./21-storage-and-caching.md">Next : Storage and caching</a></div></td></tr></table>

# Data transfer objects

A `DataToObject` turns an array — a decoded JSON payload, the answer of an external API — into a
typed object, nested objects and lists included. You describe the shape once as a constructor, and
your IDE knows every field from then on.

```php
class Album extends DataToObject
{
    public function __construct(
        public string $name,
        public Artist $artist,
    ) {}
}

class Artist extends DataToObject
{
    public function __construct(
        public string $name,
    ) {}
}

$album = Album::fromData(['name' => "People's Champion", 'artist' => ['name' => 'Käärijä']]);

$album->artist->name; // 'Käärijä'
```

A `DataToObject` holds no behaviour of its own : it is a typed view over data you did not produce.
To validate what a client sends to your routes, use a [`Request` subclass](./12-requests-and-validation.md)
instead ; to read your own tables, use [Models](./18-models.md).

## How an array is mapped

`fromData()` reads the constructor's parameters, in order, and looks for a key of the same name in
the array

| Parameter | Receives |
|---|---|
| typed with a `DataToObject` class | that class built with `fromData()` from the value |
| marked `#[ArrayOf(Item::class)]` | an array of `Item`, one per element of the value |
| marked `#[BunchOf(Item::class)]` | a [`Bunch`](./07-collections.md) of `Item` |
| anything else | the value as is |
| without a matching key | `null` |

A missing key gives `null` even when the parameter declares a default value, so make the
parameters an incoming payload may leave out nullable. For a union type, only the first type is
looked at.

When the constructor refuses the values (a `TypeError` for a wrong type, typically), `fromData()`
logs the class and the **type** of each received field — never their values, which may hold
passwords or tokens — then rethrows.

## Lists of objects

Mark a parameter with `ArrayOf` or `BunchOf` to build each element of a list

```php
class AlbumCollection extends DataToObject
{
    /**
     * @param Bunch<int,Album> $albums
     */
    public function __construct(
        #[BunchOf(Album::class)]
        public Bunch $albums,
    ) {}
}

$collection = AlbumCollection::fromData([
    'albums' => [
        ['name' => "People's Champion", 'artist' => ['name' => 'Käärijä']],
        ['name' => 'Life of a DON', 'artist' => ['name' => 'Don Toliver']],
    ],
]);

$collection->albums->key('artist.name')->toArray(); // ['Käärijä', 'Don Toliver']
```

A top-level list is mapped with the static helpers

```php
Album::array($rows);  // Album[]
Album::bunch($rows);  // Bunch<int,Album>
```

## Keys that are not valid PHP names

Override `keys()` to map a parameter to another key of the array : the parameter name on the left,
the key on the right

```php
class Invoice extends DataToObject
{
    public static function keys(): array
    {
        return [
            'invoiceNumber' => 'invoice-number',
            'totalAmount' => 'total_amount',
        ];
    }

    public function __construct(
        public ?string $invoiceNumber,
        public ?float $totalAmount,
    ) {}
}
```

## From an HTTP response

A `Response` builds one straight from its JSON body, which is the usual way to consume an external
API — see [HTTP client](./27-http-client.md)

```php
$invoice = (new Request('GET', 'https://billing.example.com/invoices/12'))
    ->fetch()
    ->toObject(Invoice::class);
```

`toObject()` throws a `RuntimeException` when the response is not a `2xx`, after logging its headers
and body.

## Generating the classes from a sample

`make:dto` writes the classes for you from an example payload

```bash
php do make:dto
```

It asks for a JSON sample, the application to write into, and the name of the root class (starting
with an uppercase letter). It writes the root class in
`<Application>/Integration/DataToObject/<Root>/<Root>.php`, and each nested object as its own class
in a sub-directory, with a `keys()` mapping the camel-cased parameters back to the original keys.

| Sample value | Generated parameter |
|---|---|
| a string | `?string` |
| an integer, a float, a boolean | `?int`, `?float`, `?bool` |
| `null` | `mixed` |
| an object | `?NestedClass`, generated alongside |
| a list of objects | `?array` marked `#[ArrayOf(NestedClass::class)]` |
| a list of scalars, an empty array | `?array` |
| a string like `2025-02-03T08:47:06.000Z` | `?\Datetime` |

Treat the result as a first draft : review the types, rename what needs it. A date parameter in
particular receives the string as is, which a `\DateTime` type refuses — change it to `?string`, or
convert the value yourself.

## The other direction : AutoDataToObject

`AutoDataToObject` is the opposite tool, for objects you build to **send** JSON. Its `toArray()`
converts the public properties, nested `AutoDataToObject` included, and `toJSON()` encodes them
(pretty-printed by default). Cube builds its [OpenAPI document](./15-api-documentation.md) this way.

PHP cannot tell an empty list from an empty object, so you tell it per key

```php
class Webhook extends AutoDataToObject
{
    public function __construct(
        public string $event,
        public array $payload = [],
        public array $tags = [],
        public array $metadata = [],
    ) {}

    protected function objectKeys(): array
    {
        return ['payload'];        // [] is encoded as {}
    }

    protected function arrayKeys(): array
    {
        return ['tags'];           // [] is encoded as []
    }

    protected function skipOnEmpty(): array
    {
        return ['metadata'];       // left out when empty
    }
}

(new Webhook('product.created'))->toJSON();
```

Nested `AutoDataToObject` instances are converted when they are a property or an element of a list
property ; inside an associative array they are kept as is.

## Reference

| Method | Does |
|---|---|
| `DataToObject::fromData(array $data)` | builds one instance |
| `DataToObject::array(array $rows)` | builds one instance per element, as an array |
| `DataToObject::bunch(array $rows)` | the same, as a `Bunch` |
| `DataToObject::keys()` | parameter name → array key, `[]` by default |
| `Response::toObject(string $class)` | builds one from a successful JSON response |
| `AutoDataToObject::toArray()` / `toJSON(int $flags = JSON_PRETTY_PRINT \| JSON_THROW_ON_ERROR)` | converts to an array, to JSON |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./19-model-apis.md">Previous : Model apis</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./21-storage-and-caching.md">Next : Storage and caching</a></div></td></tr></table>
