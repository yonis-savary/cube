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
| without a matching key | its default value, or `null` when it declares none |

Give a default value, or a nullable type, to every parameter an incoming payload may leave out :
a missing key on a parameter that has neither ends in a `TypeError`. For a union type, only the
first type is looked at.

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

## Generating a client from an OpenAPI document

When the API you consume publishes an OpenAPI document, `make:integration` generates a ready-made
[`HttpClient`](./27-http-client.md) for it : one method per operation, typed classes for what you send
and what you receive. A schema declares which fields are required, which are nullable and what a list
contains, so the generated types are exact : this is the way to prefer

```bash
php do make:integration https://billing.example.com/openapi.json --namespace='App\Integration\Billing'
```

Declare your credential in your `cube.php`, under the name of the document's security scheme, then
call the API through the client

```php
// cube.php
new BillingConfiguration(bearerAuth: env('BILLING_TOKEN')),
```

```php
$client = new BillingClient();

$product = $client->createProduct(new CreateProductRequest('Pen'));   // CreateProductResponse
$invoice = $client->getInvoicesId(12);                                // GetInvoicesIdResponse
```

It writes these classes, `<Name>` being the last segment of the namespace, or what you give with
`--client-name` :

| Class | What it is |
|---|---|
| `<Namespace>\<Name>Client` | an `HttpClient` with one method per operation |
| `<Namespace>\<Name>Configuration` | the configuration element the client reads its URL and credentials from |
| `<Namespace>\Http\Requests\<Operation>Request` | what an operation sends : its JSON body, or its query string |
| `<Namespace>\Http\Responses\<Operation>Response` | what an operation answers : its first successful (`2xx`) JSON response |
| `<Namespace>\Refs\Output\<Schema>` | a `DataToObject` per object schema, to read what you receive |
| `<Namespace>\Refs\Input\<Schema>` | an `AutoDataToObject` per object schema a request sends |

`<Operation>` is the `operationId` (`createProduct` gives `CreateProductRequest`), or the method and
path when there is none (`GET /invoices/{id}` gives `GetInvoicesIdResponse`). When a body refers to a
schema, the endpoint class only extends that schema's class, so you keep one type per endpoint. The
client and its configuration sit at the root of the namespace, so you find them first.

Without `--namespace`, the command asks for an application and writes in
`<Application>/Integration/<Title>`, `<Title>` being the PascalCased `info.title` of the document. The
classes are meant to be regenerated rather than edited : rerun the command when the document changes.

### Calling an operation

Each method is named after its operation, and takes its arguments in this order

| Argument | When |
|---|---|
| one per placeholder of the path (`int $id` for `/invoices/{id}`) | always, encoded into the URL |
| `<Operation>Request $request` | when the operation sends a body, or has query parameters |
| one per query parameter, optional ones defaulting to `null` | only when the operation also sends a body |

A request built from query parameters is optional unless one of them is required. What the method
returns depends on the response

| Response | Returned |
|---|---|
| an object | its `<Operation>Response` class |
| a list of objects | a `list` of the item class, built with `::array()` |
| anything else (no content, a file, a scalar) | the `Response` itself |

A response that is not a `2xx` throws a `RuntimeException`. A method whose name would override one of
`HttpClient` gets an `Operation` suffix (`delete` becomes `deleteOperation()`).

### Building a request

A request class is an [`AutoDataToObject`](#the-other-direction--autodatatoobject) : the required fields
are its constructor, the optional ones are properties you set. An optional property you never set is
left out of the request, and one you set to `null` is sent as `null`, which is what a partial update needs

```php
$request = new UpdateProductRequest('Pen');   // required : label
$request->unitPrice = 1.5;                     // sent as "unit-price"
$request->comment = null;                      // sent as null

$client->updateProduct(12, $request);          // the category, never set, is not sent
```

Keys that are not valid PHP names are camel-cased, and `keys()` writes them back as the document spells
them.

### Configuration and authentication

`<Name>Configuration` holds the URL, and one credential per security scheme of the document, named
after the scheme (`api_key` gives `apiKey`, `BearerAuth` gives `bearerAuth`). Declare it in your
`cube.php` with the values of your environment

```php
new PetstoreConfiguration(url: env('PETSTORE_URL'), apiKey: env('PETSTORE_API_KEY')),
```

The URL defaults to the first URL of the document's `servers`, its variables replaced by their default
value. A relative one (`/api/v3`) is resolved against the URL the document was fetched from : generated
from a file, it stays `null` until you declare it.

Each method sends the credentials of the schemes its operation accepts : its own `security`, or the
document's when it declares none (`security: []` sends none). Of those, the client sends every
credential you configured

| Security scheme | Sent as |
|---|---|
| `http` `bearer` | `Authorization: Bearer <credential>` |
| `http` `basic` | `Authorization: Basic <credential>` : give the base64 of `user:password` |
| `apiKey` in a header, the query string or a cookie | the credential under the declared name |
| `oauth2`, `openIdConnect` | `Authorization: Bearer <credential>` : the flow is not run, give an access token |

### Reading the document

Give the document first, before any option : a path relative to your project root, or an `http(s)`
URL to fetch it from. Only JSON documents are read : convert a YAML document before generating.

When the API serves its document behind authentication, pass the headers the request needs with
`--header`, as `"Name: value"`. Repeat it to send several

```bash
php do make:integration https://billing.example.com/openapi.json --header='Authorization: Bearer my-token'
```

The command stops without writing anything when the server answers anything but a `2xx`, or when its
answer is not a JSON OpenAPI 3 document.

### Required and nullable fields

The command is strict by default : a field the schema requires is a parameter without default, and only
a nullable field accepts `null`. `required`, `nullable: true` (OpenAPI 3.0) and `type: [..., "null"]`
(OpenAPI 3.1) are all read

```php
// Refs\Output\Product : required [id, label], tags is optional
public function __construct(
    public int $id,
    public string $label,
    public ?array $tags = null,
) {}
```

A strict class throws a `TypeError` from `fromData()` when the API leaves out a field it declares as
required. If the API does not respect its own document, generate with `--lenient` : every property
then becomes optional and nullable (`public ?int $id = null`).

### Generating only some endpoints

By default, every schema and every operation is generated. `--endpoint` restricts the generation to
one operation, given as its `operationId` or as `"METHOD /path"`, and to the schemas it uses. Repeat it
to select several

```bash
php do make:integration billing-openapi.json --endpoint=createProduct --endpoint='GET /invoices/{id}'
```

The command stops without writing anything when an endpoint matches no operation.

### How schemas are mapped

| Schema | Generated property |
|---|---|
| `string`, `integer`, `number`, `boolean` | `string`, `int`, `float`, `bool` |
| `string` with a `format` (`date-time`, `uuid`...) | `string` : parse it where you read it |
| an `enum` | its scalar type, documented as `'draft'\|'paid'` |
| an object with `properties`, inline or through `$ref` | a class |
| `allOf` | one class holding the properties of every part |
| an `array` of objects | `array` of that class, marked `#[ArrayOf(Item::class)]` on what you receive |
| an `array` of anything else | `array`, documented as `list<string>`... |
| an object with `additionalProperties` only | `array`, documented as `array<string,string>`... |
| `oneOf` / `anyOf` with several non-null variants | `mixed` |
| a `$ref` to a non-object schema (an enum, a list) | the type of that schema, without a class |
| a `readOnly` property | left out of the `Input` classes |
| a `writeOnly` property | left out of the `Output` classes |

Inline objects become classes named after their parent and their key (`lines` items of `Invoice` give
`InvoiceLinesItem`). Those of a request or a response go to a sub-namespace named after it, under their
key alone : the `owner` of `GetDirectoryResponse` is `Http\Responses\GetDirectoryResponse\Owner`. Schema names that are not valid PHP class names are PascalCased, and PHP reserved
words get a `Dto` suffix (`List` gives `ListDto`). When two keys of one schema give the same property
name (`first_name` and `firstName`), the later one gets a number (`$firstName1`, then `$firstName2`).

The client follows the same rule wherever two names collide :

| Collision | Generated names |
|---|---|
| two operations without `operationId` (`GET /order-lines`, `GET /order_lines`) | `getOrderLines()`, `getOrderLines1()` and their `GetOrderLines1Request`... |
| a path parameter, the request and a query parameter (`{id}` and `?id=`) | `$id`, `$id1`, in the signature order |
| two security schemes (`api_key`, `apiKey`) | `$apiKey`, `$apiKey1` in the configuration |
| a security scheme named `url` | `$url1`, as `$url` holds the base URL |

Header and cookie parameters are not generated yet. A list in the query is sent as one parameter per
item (`?tags=friendly&tags=young`), what OpenAPI expects by default (`explode: true`) : `style` and
`explode: false` are not read yet. The other values are encoded by `http_build_query()` (a boolean
becomes `1` or `0`). Only references inside the document
(`#/components/...`) are followed : a reference to another file stops the generation.

## Generating the classes from a sample

Without `--namespace`, the classes go to `<Application>/Integration/<document title>`. A title like
`Swagger Petstore - OpenAPI 3.0` gives a long `SwaggerPetstoreOpenAPI30Client` : name the client
yourself and keep the directory

```bash
php do make:integration petstore-openapi.json --client-name=Petstore   # PetstoreClient, PetstoreConfiguration
```

When the API publishes no OpenAPI document, or for a webhook documented only by an example,
`make:dto` writes the classes from a sample payload instead. Give the sample as a file, an `http(s)`
URL, or the JSON text itself, and the name of the root class with `--name`

```bash
php do make:dto storage/album-sample.json --name=Album
php do make:dto https://music.example.com/albums/1 --name=Album --header='Authorization: Bearer my-token'
php do make:dto '{"name": "Blue Train", "artist": {"name": "John Coltrane"}}' --name=Album
```

Without an argument, the command asks for the JSON sample, and it asks for the root class name when
`--name` is missing. The sample must be a JSON object : for an endpoint answering a list, give one of
its elements.

It asks for the application to write into, then writes the root class in
`<Application>/Integration/DataToObject/<Root>/<Root>.php`, and each nested object as its own class
in a sub-directory. Keys that are not valid camel-cased PHP names (`created_at`, `unit-price`) get a
`keys()` entry mapping the parameter back to them.

Pass `--namespace` to choose where the classes go instead of picking an application. The namespace must
sit under a PSR-4 prefix of your `composer.json`, so that the classes can be autoloaded

```bash
php do make:dto album.json --name=Album --namespace='App\Integration\Spotify'
# App\Integration\Spotify\Album\Album, App\Integration\Spotify\Album\Artist\Artist...
```

| Sample value | Generated parameter |
|---|---|
| a string, dates included | `?string` |
| an integer, a float, a boolean | `?int`, `?float`, `?bool` |
| `null` | `mixed` |
| an object | `?NestedClass`, generated alongside |
| a list of objects | `?array` marked `#[ArrayOf(NestedClass::class)]` |
| a list of scalars | `?array`, documented as `list<string>` (or the type of the first element) |
| an empty array | `?array` |

Treat the result as a first draft : review the types, rename what needs it. `fromData()` passes
scalars as is, so a date stays the string of the payload — parse it where you read it.

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

Only public **initialized** properties are exported : a typed property without default that you never
set is left out, while one you set to `null` is exported as `null`. Nested `AutoDataToObject` instances
are converted wherever they sit : a property, an element of a list, or a value of an associative array.

Override `keys()` to write a property under another key, the mirror of
[`DataToObject::keys()`](#keys-that-are-not-valid-php-names) : the property name on the left, the key on
the right. `objectKeys()`, `arrayKeys()` and `skipOnEmpty()` keep naming properties

```php
class InvoiceLine extends AutoDataToObject
{
    public function __construct(
        public int $unitCount,
    ) {}

    public static function keys(): array
    {
        return ['unitCount' => 'unit-count'];
    }
}

(new InvoiceLine(3))->toArray();   // ['unit-count' => 3]
```

## Reference

| Method | Does |
|---|---|
| `DataToObject::fromData(array $data)` | builds one instance |
| `DataToObject::array(array $rows)` | builds one instance per element, as an array |
| `DataToObject::bunch(array $rows)` | the same, as a `Bunch` |
| `DataToObject::keys()` | parameter name → array key, `[]` by default |
| `Response::toObject(string $class)` | builds one from a successful JSON response |
| `AutoDataToObject::toArray()` / `toJSON(int $flags = JSON_PRETTY_PRINT \| JSON_THROW_ON_ERROR)` | converts to an array, to JSON |
| `AutoDataToObject::keys()` | property name → exported key, `[]` by default |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./19-model-apis.md">Previous : Model apis</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./21-storage-and-caching.md">Next : Storage and caching</a></div></td></tr></table>
