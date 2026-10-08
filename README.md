# Cube PHP

Cube is a light framework that provides every essential tool needed to build back-end projects and automation tools.

You can find the framework's documentation in the [`/docs`](./docs/README.md) directory !

## AI Usage

Cube source code should not be generated : AI can be used to improve readability or performance, but source code should still be handwritten.

Tests can be automatically written by agents.

## 🧰 Features

- 🌐 Web
  - Fast Routing
  - Request Validation
  - Middleware
  - Static File Serving
  - Unix Socket Server (for internal APIs)

- 🔩 Framework
  - Full DocBlock support ! (No magic methods, every type is documented)
  - PHP Configuration
  - CLI Commands
  - Routine tools (Scheduling / Queueing)

- 🌳 Environment
  - Cache (Files, DB or Redis)
  - Session (Files, DB or Redis)
  - Directory manipulation (Local Disk / FTP)
  - PSR Logging

- 💿 Data
  - Model Manipulation (Supported DBMS : MySQL/MariaDB, SQLite, Postgres)
  - Automatic Model Generation !
  - DB Migration system !
  - Password Authentication System (+ Support for Custom Authentication)

## 🔥 Installation

```bash
# Install in your repository
composer require yonis-savary/cube

# Install server base files such as Public/, .gitignore...
cp -r vendor/yonis-savary/cube/server/* .
```

## 🔍 Cube's Look

A controller is discovered on its own, its routes are declared next to their callbacks

```php
class ProductController extends Controller
{
    public function __constructor(
      protected MyProductService $productService
    ) {
      // Dependency injection is supported for many features!
    }

    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/product/{int:id}', [self::class, 'getProduct']),
            Route::post('/product', [self::class, 'storeProduct']),
            //...
        );
    }

    // Slug values is resolved through parameter type (404 when it does not exist)
    public static function getProduct(Request $request, Product $product)
    {
        return $product;
    }

    // The request is validated before the callback runs (422 when it is not)
    public static function storeProduct(StoreProductRequest $request)
    {
        $product = Product::fromRequest($request);
        $product->save();

        return Response::json($product, StatusCode::CREATED);
    }
}
```

Input shapes are declared once, in a `Request` subclass

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

Queries are built fluently, and run on MySQL, Postgres or SQLite alike

```php
$cheapest = Product::select(with: ['provider'])
    ->where('price_dollar', 20, '<')
    ->order('price_dollar', 'ASC')
    ->limit(10)
    ->fetch();

$product = Product::find(4);
$product->name = 'monitor';
$product->save();
```

A command is a class too : this one runs with `php do app:count-products`

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

## 📈 Development

```sh
# Testing the framework
make test
```
