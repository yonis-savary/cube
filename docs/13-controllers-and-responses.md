<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./12-requests-and-validation.md">Previous : Requests and validation</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./14-views-and-assets.md">Next : Views and assets</a></div></td></tr></table>

# Controllers and responses

A `Controller` groups the routes of one concern and the callbacks behind them, and each callback
answers with a `Response` — or with a model, a collection or an array that Cube turns into JSON. A
controller is discovered by class : dropping the file in your application is all it takes to
register it, there is no list to edit.

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
        $product = Product::fromRequest($request)->save();

        return Response::json($product, StatusCode::CREATED);
    }

    public static function getProduct(Request $request, Product $product)
    {
        return $product; // 200, the product as JSON
    }
}
```

Route declaration itself — methods, slugs, groups, middlewares, callback parameters — lives in
[Routing and middlewares](./11-routing-and-middlewares.md), and reading the input in
[Requests and validation](./12-requests-and-validation.md). This page covers what a controller
answers.

## Static or instance methods

A route callback can be a static method or an instance method of the controller

- a **static** method is called as is, without building the controller
- an **instance** method is called on a controller built by the `Injector` for that call, so its
  constructor receives its dependencies (see [Dependency injection](./05-dependency-injection.md))

```php
class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
    ) {}

    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post('/invoices/{invoice}/send', [self::class, 'send']),
        );
    }

    public function send(Request $request, Invoice $invoice): Response
    {
        $this->invoices->send($invoice);

        return Response::noContent();
    }
}
```

The controller is also built once when the routes are loaded, to call `routes()` : keep its
constructor free of side effects.

## What a callback returns

Return a `Response` when you want control over the status, headers or body. Anything else is
converted for you

| Callback returns | Client receives |
|---|---|
| a `Response` | that response |
| nothing, or `null` | `204 No Content`, empty body |
| a `Model` | `200`, the model as a JSON object, every field and every loaded relation |
| a `Bunch` or an array | `200`, a JSON array or object, models inside converted the same way |
| any other value | `200`, that value encoded as JSON — a string answers `"OK"`, quotes included |

```php
public static function list()
{
    return Product::select()->fetchBunch(); // 200, JSON array of products
}
```

Use `Response::json(null)` when you really mean to send a JSON `null`, and `Response::text()` for a
plain-text answer.

A model answered as is leaves your application whole, a password hash included : answer sensitive
models through a [`ModelResource`](#shaping-what-a-model-answers).

Type your callbacks' return (`: Product`, `: ?Product`, `: void`) : the
[API documentation](./15-api-documentation.md) reads it.

## Building a response

`Response` has one named constructor per status code, so the status is in the call rather than in
an argument. They take an optional **string** body and set no content type

```php
return Response::noContent();
return Response::notFound('No such product');
return Response::unprocessableContent('Array expected');
return Response::serviceUnavailable('Down for maintenance');
```

To answer data, use a constructor that encodes it and sets the content type

```php
Response::json($product);                                   // application/json, models and Bunch converted
Response::json($product, StatusCode::CREATED);              // with another status
Response::text('No such product', StatusCode::NOT_FOUND);   // text/plain, safe for a message echoing input
Response::html(render('order/details', ['order' => $order])); // text/html
Response::file($storage->path('invoice.pdf'));              // streams a file, type read from its extension
Response::file($path, attachmentFile: 'invoice.pdf');       // as a download named invoice.pdf
Response::download($path);                                  // as a download named after the file
```

| Constructor | Does |
|---|---|
| `Response::json(mixed $value, int $code = 200)` | encodes `$value`, converting models and `Bunch` recursively |
| `Response::text(string $value, int $code = 200)` | `text/plain` |
| `Response::html(mixed $value, int $code = 200)` | `text/html`, see [Views and assets](./14-views-and-assets.md) |
| `Response::file(string $path, int $code = 200, ?string $attachmentFile = null)` | streams `$path` ; with `$attachmentFile`, as a download under that name |
| `Response::download(string $path, int $code = 200, ?string $attachmentFile = null)` | `file()` as a download, named after the file by default |
| `Response::movedPermanently(string $location)`, `found()`, `seeOther()`, `temporaryRedirect()`, `permanentRedirect()` | `301`, `302`, `303`, `307`, `308` with a `Location` header |
| `Response::ok()`, `created()`, `notFound()`, `forbidden()`, … | the status, with an optional content : a string is sent as is, anything else (a model, a `Bunch`, an array) as JSON like `json()` |
| `new Response(int $statusCode = 204, ?string $body = null, array $headers = [])` | anything else |

### Decorating a response

Three fluent steps decorate a response

| Step | Effect |
|---|---|
| `withHeaders(array $headers)` | adds response headers |
| `withClientCaching(int $timeToLive)` | sets `Cache-Control: max-age`, in seconds |
| `withCORSHeaders(Request $request, ?array $allowedMethods = null)` | adds the CORS headers answering the request's origin, when the configuration allows it (see [CORS](./11-routing-and-middlewares.md#cross-origin-requests-cors)) |

```php
return Response::file($storage->path('logo.png'))->withClientCaching(Cache::DAY);

return Response::seeOther('/products')->withHeaders(['Cache-Control' => 'no-store']);
```

## Aborting from deep inside

A service several calls away from the controller can end the request : throw a `ResponseException`
carrying the finished response, and the router sends it as is

```php
class StockService
{
    public function reserve(Product $product, int $quantity): void
    {
        if ($product->stock < $quantity)
            throw new ResponseException(
                "Not enough stock for product {$product->id}",
                Response::conflict('Not enough stock')
            );

        // ...
    }
}
```

The message is for your logs, the response for the client. This is how Cube itself answers `422`
for an invalid request and `404` for a missing slug model. The router catches it around the
middlewares, the callback and a `WebAPI`'s `handle()`.

## Claiming a request before routing

A `WebAPI` can answer for a request *before* the router walks its route tree. Override `handle()`
and return

| Return | Meaning |
|---|---|
| a `Response` | you answered, the router sends it |
| a `Route` | you decided which route runs, behind the `commonMiddlewares` like any other |
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

Register it in the `apis` option of your `RouterConfiguration`, as a class name or an instance :
`WebAPI` services are not discovered. A `WebAPI` can also declare routes in its own `routes()`
method, like a controller.

```php
new RouterConfiguration(
    apis: [MaintenanceAPI::class],
)
```

The router asks every configured `WebAPI`, in order, before matching routes, and before a route
remembered by the route cache. `StaticServer` and
`AssetServer` are `WebAPI` services (see [Static server](./32-static-server.md) and
[Views and assets](./14-views-and-assets.md#serving-assets)).

## A CRUD API from a model

`ModelAPI` does the create, read, update and delete work over one model, behind routes you declare
in a controller. It has its own page : [Model APIs](./19-model-apis.md).

## Shaping what a model answers

A model returned as is leaves your application whole : every field and every loaded relation, a
password hash included. A `ModelResource` decides what it looks like instead, and you apply it
explicitly where the model is answered

```php
/**
 * @extends ModelResource<User>
 */
class UserResource extends ModelResource
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    public function toArray(Model $model): array
    {
        return [
            'id' => $model->id,
            'login' => $model->login,
            'agency' => AgencyResource::of($model->agency), // a model left as is would be answered whole
        ];
    }
}
```

`of()` applies it to one model, or to an array or a `Bunch` of them

```php
return UserResource::of($user);                            // one object
return UserResource::of(User::select()->fetchBunch());     // a list
return ['everyone' => UserResource::of($users)];           // nested anywhere
```

`of()` needs a model or a list : guard a relation that may be `null` before shaping it. Load
relations in the controller and read them in the resource, rather than calling `load()` inside it :
the endpoint knows what it loads, and declares it to the API documentation. The resource is built by
the `Injector`, so its constructor can receive dependencies.

A model can have as many resources as it has representations : nothing is picked for you. A
`ModelAPI` answers with the one its `getModelResource()` gives (see
[Model APIs](./19-model-apis.md)), and the API documentation describes it with
[`#[ResourceResponse]`](./15-api-documentation.md#describing-a-response).

## Large uploads

A file too big for one request is sent in pieces. `LargeUploadManager` gives each transfer an
identifier and a directory, collects the chunks as they arrive, and concatenates them when the client
says it is done.

```
client                          your controller                   Storage
  |                                   |                              |
  |-- POST /uploads ----------------->| $manager->start()            |
  |<-- identifier --------------------|                     large-upload-temp/<id>/
  |                                   |                              |
  |-- PUT /uploads/<id>/0 ----------->| $upload->addChunk(0, $body)  chunk-0
  |-- PUT /uploads/<id>/1 ----------->| $upload->addChunk(1, $body)  chunk-1
  |                                   |                              |
  |-- POST /uploads/<id>/done ------->| $upload->wrap($destination)  -> one file
  |<-- final path --------------------|                     temp directory removed
```

Chunk numbers decide the order, not arrival time, so the client may send them in parallel.

Here are the three routes

```php
class UploadController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post('/uploads', [self::class, 'beginUpload']),
            Route::put('/uploads/{identifier}/{int:number}', [self::class, 'pushChunk']),
            Route::post('/uploads/{identifier}/done', [self::class, 'finishUpload']),
        );
    }

    // ...
}
```

### Opening a transfer

`start()` creates the identifier and its directory. Give the identifier back to the client — every
following call needs it.

```php
public static function beginUpload(Request $request)
{
    $upload = LargeUploadManager::getInstance()->start();

    return Response::json(['identifier' => $upload->identifier]);
}
```

### Receiving chunks

`find()` returns the transfer, or `null` when the identifier is unknown — which is also how you
reject a client inventing one.

```php
public static function pushChunk(Request $request, string $identifier, int $number)
{
    if (!$upload = LargeUploadManager::getInstance()->find($identifier))
        return Response::notFound('Unknown upload');

    if (!$upload->addChunk($number, $request->getBody()))
        return Response::contentTooLarge('Upload too large');

    return Response::noContent();
}
```

A chunk is written as `chunk-<number>` in the transfer's directory. Sending the same number twice
overwrites it, so a client can retry a failed piece without restarting.

### Closing it

`wrap()` concatenates the chunks in natural order into one file, and returns its path. The transfer
directory is removed afterwards unless you ask otherwise.

```php
public static function finishUpload(Request $request, string $identifier)
{
    if (!$upload = LargeUploadManager::getInstance()->find($identifier))
        return Response::notFound('Unknown upload');

    $path = $upload->wrap(Storage::getInstance()->child('documents'));

    return Response::json(['path' => $path]);
}
```

The destination file is named after the identifier. `wrap()` **throws** an
`InvalidArgumentException` when that name already exists in the destination, rather than
overwriting — rename or move the result if you want a name of your own.

```php
$path = $upload->wrap($destination, cleanup: false); // keep the chunks
```

### Giving up

An abandoned transfer leaves its chunks behind. `delete()` removes the directory and everything in
it, and answers `false` when the identifier is unknown.

```php
LargeUploadManager::getInstance()->delete($identifier);
```

Nothing expires old transfers on its own : schedule a cleanup with `deleteOlderThan()`, which
removes the transfers whose `info.json` file is older than the given number of seconds (see
[Schedules](./28-schedules.md))

```php
// App/Schedules/large-uploads.php
Cube\schedule('0 * * * *', fn () => LargeUploadManager::getInstance()->deleteOlderThan(Cache::DAY));
```

### Reference

| `LargeUploadManager` | Does |
|---|---|
| `start()` | opens a transfer and returns the `LargeUpload` |
| `find(string $identifier)` | returns the transfer, `null` when unknown |
| `delete(string $identifier)` | removes it, `false` when unknown |
| `deleteOlderThan(int $seconds)` | removes the transfers started more than `$seconds` ago, returns how many |

| `LargeUpload` | Does |
|---|---|
| `$identifier` | the public identifier, readonly |
| `$storage` | the `Storage` holding this transfer's chunks, readonly |
| `$maxSize` | the size limit of the transfer, readonly |
| `addChunk(int $chunkNumber, string $body)` | writes one chunk, `false` (and nothing written) when it would go over `maxSize` |
| `wrap(Storage $destination, bool $cleanup = true)` | concatenates and returns the final path |
| `delete()` | removes the chunks and the directory |

Each transfer directory also holds an `info.json` file with its identifier and `creation_date`.

Configure the working directory in your `cube.php`

```php
new LargeUploadManagerConfiguration(
    storageName: '/large-upload-temp',
    maxSize: File::GIGABYTES * 2,
)
```

| Option | Default | Does |
|---|---|---|
| `storageName` | `/large-upload-temp` | directory, inside your `Storage`, holding the transfers |
| `maxSize` | `null` | maximum size of a transfer in bytes, all chunks together, `null` for no limit |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./12-requests-and-validation.md">Previous : Requests and validation</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./14-views-and-assets.md">Next : Views and assets</a></div></td></tr></table>
