<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./106-routing-and-middlewares.md">Previous : Routing and middlewares</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./108-storage-and-caching.md">Next : Storage and caching</a></div></td></tr></table>

# Controllers and APIs

A `Controller` groups the routes of one concern and the callbacks behind them. It is discovered by
class name — dropping the file in your application's directory is all it takes to register it, no
list to edit.

Here is a complete one

```php
class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post('/product', [self::class, 'storeProduct']),
            Route::get('/product/{int:id}', [self::class, 'getProduct']),
        );
    }

    public static function storeProduct(StoreProductRequest $request)
    {
        $product = Product::fromRequest($request);
        $product->save();

        return Response::json($product);
    }

    public static function getProduct(Request $request, Product $product)
    {
        return $product;
    }
}
```

Route declaration itself — methods, slugs, groups, middlewares, injected parameters — lives in
[Routing and middlewares](./106-routing-and-middlewares.md). This page covers what you write
*inside* a controller.

## Reading the request

Every callback can take a `Request` as its first parameter. It has already normalized the incoming
data : `"true"`, `"off"` and `"null"` are real scalars, and a JSON body has been decoded into the
POST bag.

```php
$name = $request->param('name');                   // one value, GET or POST
$name = $request->param('name', 'unnamed');        // with a fallback
[$page, $size] = $request->list(['page', 'size']); // several, positionally
$filters = $request->params(['name', 'price']);    // several, keyed
```

| Method | Gives you |
|---|---|
| `param(string $name, mixed $default = null)` | one value, GET first then POST |
| `params(array $keys, array $default = [])` | those keys, as an associative array |
| `list(array $keys, array $default = [])` | those keys, as a positional list to destructure |
| `only(array $keys)` | validated data restricted to those keys |
| `collect(string $key)` | one value wrapped in a `Bunch` |
| `all(bool $getParamsGotPriority = true)` | GET and POST merged |
| `get()` / `post()` | one bag only |
| `upload(string $inputName)` | a single `Upload`, `null` when absent |
| `uploads(string $inputName)` | every `Upload` sent under that name |
| `getMethod()` / `getPath()` / `getIp()` / `getCookies()` | the request line and its context |

`getRoute()`, `getSlugValues()` and `getSlugObject()` give you back what the router matched.

## Validating input

Declare the expected shape in a `Request` subclass. The router validates **before** your callback
runs and answers `422` on its own, so the body of a controller never re-checks its input.

```php
class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            'name' => Param::string(),
            'price_dollar' => Param::float(nullable: true),
            'managers' => Param::array(Param::object()),
        ];
    }
}
```

Type the parameter with your subclass, then read the coerced values

```php
public static function storeProduct(StoreProductRequest $request)
{
    $name = $request->validated('name');   // trimmed string
    $all = $request->validated();          // every validated value
}
```

`Param` composes the shape ; each factory takes a `nullable` flag as its last argument

| Factory | Accepts |
|---|---|
| `Param::string(bool $trim = true)` | a string, trimmed by default |
| `Param::integer()` | an int, or a string writing a whole number (`"12"`, not `"1.9"` nor `" 5"`) |
| `Param::float()` | a number, coerced to `float` |
| `Param::boolean()` | `true`/`false`, and the strings the request normalized |
| `Param::email()` / `Param::url()` / `Param::uuid()` | a validated format |
| `Param::date()` / `Param::datetime()` | a date that exists in the calendar, `datetime` can add a missing time |
| `Param::object(array $rules)` | a nested shape, recursing into `$rules` |
| `Param::array(Rule $childRule)` | a list whose every item matches `$childRule` |
| `Param::anyKeyObject(Rule $valueRule)` | a map with unknown keys and known value shape |
| `Param::model(...)` | a value that must exist as a model row |
| `UploadRule::new()` | an upload, chain `withMimeType()` and `withMaxSize()` |

Two chainable checks apply to any of them : `inArray(array $values)` and
`isBetween($min, $max, bool $canBeEqual = true)`. Add your own with `withCondition()` and
`withTransformer()` — on `object()` and `array()` too, where they receive the validated children.
Steps stop at the first failed check, so a condition never sees a value an earlier check refused

```php
Param::object(['password' => Param::string(), 'confirmation' => Param::string()])
    ->withCondition(fn (array $value) => $value['password'] === $value['confirmation'], 'passwords do not match');
```

Uploads are validated alongside the body — the rule key is the input name

```php
class StoreDocumentRequest extends Request
{
    public function getRules(): array
    {
        return [
            'to-upload' => UploadRule::new()
                ->withMimeType('application/json')
                ->withMaxSize(File::KILOBYTES * 5),
        ];
    }
}
```

You can also validate by hand when you need the errors rather than a `422` :
`$request->validate()` returns a `ValidationReturn` holding both the errors keyed by path and the
coerced result, and `$request->isValid()` answers the question directly.

## Answering

`Response` has one named constructor per status code, so the status is in the call rather than in an
argument

```php
return Response::ok($product);
return Response::created($product);
return Response::noContent();
return Response::notFound('No such product');
return Response::unprocessableContent('Array expected');
```

A few constructors do more than set a code

```php
Response::json($product);                       // encodes and sets the content type
Response::text('No such product', StatusCode::NOT_FOUND); // text/plain, safe for a message echoing input
Response::html('<h1>Hello</h1>');               // text/html
Response::file($storage->path('invoice.pdf'));  // streams a file
Response::file($path, attachmentFile: 'invoice.pdf'); // as a download
```

And three fluent steps decorate a response

| Step | Effect |
|---|---|
| `withHeaders(array $headers)` | adds response headers |
| `withClientCaching(int $timeToLive)` | sets the caching headers, in seconds |
| `withCORSHeaders(?array $allowedMethods = null)` | adds the CORS headers for this response |

Returning a `Model`, a `Bunch` or an array from a callback is enough — the router converts it to a
JSON response for you, as described in
[Routing and middlewares](./106-routing-and-middlewares.md).

To abort from deep inside a service, throw a `ResponseException` carrying the finished response ;
the router unwraps it and sends it as-is.

## Claiming a request before routing

A `Controller` is a `WebAPI`, and a `WebAPI` can answer for a request *before* the router walks its
route tree. Override `handle()` and return

| Return | Meaning |
|---|---|
| a `Response` | you answered, the router sends it |
| a `Route` | you decided which route runs |
| `null` | not your business, routing continues |

```php
class MaintenanceAPI extends WebAPI
{
    public function handle(Request $request): Response|Route|null
    {
        if (!Storage::getInstance()->isFile('maintenance.lock'))
            return null;

        return Response::serviceUnavailable('Down for maintenance');
    }
}
```

A `WebAPI` that is not a `Controller` is not discovered — register it in the `apis` option of your
`RouterConfiguration`.

## A CRUD API from a model

`ModelAPI` does the CRUD work over one model. It declares no route : your controller declares
them, receives the `ModelAPI` by injection and delegates to it. Extend it once per model

```php
/**
 * @extends ModelAPI<Product>
 */
class ProductModelAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }
}
```

Then wire it in a controller. Its constructor receives the `ModelAPI`, so its route methods are
instance methods

```php
class ProductController extends Controller
{
    public function __construct(
        protected ProductModelAPI $products
    ) {}

    public function routes(Router $router): void
    {
        $router->group('/product', routes: [
            Route::post('/', [self::class, 'create']),
            Route::get('/', [self::class, 'read']),
            new Route('/{product}', [self::class, 'update'], ['PUT', 'PATCH']),
            Route::delete('/{product}', [self::class, 'delete']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->products->createItems($request);
    }

    /** @return Product[] */
    public function read(Request $request): array
    {
        return $this->products->readItems($request);
    }

    // The slug becomes the Product, Cube answers 404 when it does not exist
    public function update(Request $request, Product $product): Product
    {
        return $this->products->updateItem($product, $request);
    }

    public function delete(Request $request, Product $product): Response
    {
        return $this->products->deleteItem($product);
    }
}
```

Since the routes are yours, so is everything around them : expose only the verbs you need, put them
behind a middleware, add OpenAPI attributes or a typed `Request` like on any other route.

When you have nothing to override, `forModel()` builds a `ModelAPI` without writing a class

```php
public function read(Request $request): array
{
    return ModelAPI::forModel(Product::class)->readItems($request);
}
```

Prefer the subclass as soon as you want to inject it : the `Injector` resolves a class by its type,
which an anonymous class does not have.

| Method | Does | Answers |
|---|---|---|
| `createItems(Request)` | creates one row, or several when the JSON body is a list | `201` with the created rows, `422` (plain text) when the JSON body is not an object or a list |
| `readItems(Request)` | reads rows, filtered by any field given as a parameter | the matching models |
| `updateItem(Model, Request)` | updates the given row with the request fields, the primary key excepted | the updated model |
| `deleteItem(Model)` | deletes the given row | `200` |

`updateItem()` and `deleteItem()` throw an `InvalidArgumentException` when the item is not an
instance of the class `getModelClass()` returns.

Reading deserves a note : a parameter matching a `STRING` field becomes a `LIKE` search, split on
spaces, every word having to match. Any other field type is compared for equality.

```
GET /product?name=blue screen   ->  name LIKE '%blue%' AND name LIKE '%screen%'
GET /product?id=12              ->  id = 12
```
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./106-routing-and-middlewares.md">Previous : Routing and middlewares</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./108-storage-and-caching.md">Next : Storage and caching</a></div></td></tr></table>
