<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./README.md">Previous : Readme</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./02-installation.md">Next : Installation</a></div></td></tr></table>

# Introduction

Cube is a back-end web framework for PHP 8.5, installed as a composer library : routing, request
validation, models and migrations, commands, queues, scheduling and websockets, with no front-end
layer on top.

Here is what a small application looks like : a controller declares its routes next to their
callbacks

```php
class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/product/{int:id}', [self::class, 'getProduct']),
            Route::post('/product', [self::class, 'storeProduct']),
        );
    }

    // The slug becomes Product::find($id) : a missing row answers 404
    public static function getProduct(Request $request, Product $product): Product
    {
        return $product;
    }

    // The request is validated before this body runs : invalid input answers 422
    public static function storeProduct(StoreProductRequest $request): Response
    {
        $product = Product::fromRequest($request);
        $product->save();

        return Response::json($product, StatusCode::CREATED);
    }
}
```

Dropping this file in your application is enough : Cube finds the controller by itself, there is no
list of routes or controllers to update.

## Principles

Four rules shape every part of Cube, and the code you write with it.

| Principle | What it means for you |
|---|---|
| Back-end focus | Cube answers HTTP requests, runs commands and background work. Views are plain PHP files, there is no template engine nor front-end framework. |
| No useless abstraction | A class exists because it carries behaviour or data. Adding a controller, a command or a migration is adding one file : nothing has to be registered. |
| Full IDE support, no magic | No facades, no `__call`, no string-keyed service container. Configuration is typed objects, collections and queries are generic in PHPDoc : your IDE knows every type and every option. |
| Shortest readable code | Fluent builders, guard clauses, sensible defaults : the common case takes a few lines. |

## A short tour

Input shapes are declared once, in a `Request` subclass. See
[Requests and validation](./12-requests-and-validation.md)

```php
class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            'name' => Param::string(),
            'price_dollar' => Param::float(nullable: true),
        ];
    }
}
```

Queries are built fluently, and run on MySQL, Postgres or SQLite alike. See
[Database and queries](./16-database-and-queries.md) and [Models](./18-models.md)

```php
$cheapest = Product::select()
    ->where('price_dollar', 20, '<')
    ->order('price_dollar', 'ASC')
    ->limit(10)
    ->fetch();

$product = Product::find(4);
$product->name = 'monitor';
$product->save();

Product::delete()->where('price_dollar', 0)->fetch();
```

A command is a class too : this one runs with `php do app:count-products`. See
[Commands](./10-commands.md)

```php
class CountProducts extends Command
{
    public function execute(Args $args): int
    {
        Console::log(Product::select()->count());

        return 0;
    }
}
```

And a few other tools, each covered on its own page

```php
// Logging, see Logging
Logger::getInstance()->info('Created product {product}', ['product' => $product->id]);

// Collections, see Collections
Bunch::wrap([1, 2, 3])
    ->map(fn ($x) => $x * 2)
    ->push(8)
    ->get(); // [2, 4, 6, 8]

// HTTP client, see HTTP client
$response = (new Request('GET', 'https://api.example.org/products'))->fetch();
$response->getBody();

// Scheduling, see Schedules
Cube\everyMinute([StockSynchronizer::class, 'synchronize'], 5);   // every 5 minutes, in its own process
```

Pages : [Logging](./09-logging.md), [Collections](./07-collections.md),
[HTTP client](./27-http-client.md), [Schedules](./28-schedules.md).

## Reading this documentation

The pages follow one reading order, from the foundations to the most specific tools :

- [Installation](./02-installation.md) to [Commands](./10-commands.md) — setting up a project,
  configuring it, and the core mechanics everything else relies on (discovery, injection,
  components, collections, events, logging).
- [Routing and middlewares](./11-routing-and-middlewares.md) to
  [API documentation](./15-api-documentation.md) — answering HTTP requests.
- [Database and queries](./16-database-and-queries.md) to
  [Data transfer objects](./20-data-transfer-objects.md) — the data layer.
- [Storage and caching](./21-storage-and-caching.md) to [Static server](./32-static-server.md) —
  sessions, security, background work and other services.
- [Testing](./33-testing.md) and [Deployment](./34-deployment.md) — shipping your application.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./README.md">Previous : Readme</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./02-installation.md">Next : Installation</a></div></td></tr></table>
