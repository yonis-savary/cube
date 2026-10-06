<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./105-logging.md">Previous : Logging</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./107-controller-and-apis.md">Next : Controller and apis</a></div></td></tr></table>

# Routing and middlewares

The `Router` component is what turns a `Request` into a `Response` : it holds your routes, finds
the one matching the incoming request, resolves the callback parameters and executes the
middlewares around it.

```
Request ---> Router ---> [ middlewares ] ---> your callback ---> Response
```

## Declaring routes

Routes can be declared in three places, and Cube loads all of them for you

- in a **controller**, through its `routes()` method (controllers are found automatically)
- in a **routes file**, any PHP file inside your app `Routes/` (or `Router/`) directory, where the
  `$router` variable is available
- in a **service**, any `WebAPI` class registered in your `RouterConfiguration`

```php
class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/products', [self::class, 'list']),
            Route::post('/products', [self::class, 'store']),
        );
    }
}
```

`addRoutes()` accepts `Route` objects, `null` (handy for conditional routes) and callables
receiving the router, so you can split big route sets into functions.

### Route methods

| Factory | Accepted HTTP methods |
|---------|-----------------------|
| `Route::get($path, $callback)` | `GET` |
| `Route::post($path, $callback)` | `POST` |
| `Route::put($path, $callback)` | `PUT` |
| `Route::patch($path, $callback)` | `PATCH` |
| `Route::delete($path, $callback)` | `DELETE` |
| `Route::options($path, $callback)` | `OPTIONS` |
| `Route::any($path, $callback)` | any method |
| `Route::file($path, $file)` | `GET`, and serves `$file` directly |

Every factory takes the same optional parameters : `$middlewares` and `$extras`

```php
Route::get('/invoices', [InvoiceController::class, 'list'], [AuthMiddleware::class], ['scope' => 'billing'])
```

A callback is either an array `[SomeClass::class, 'method']` or a closure. **Prefer the array
form** : it is the only one the framework can reflect on, which is what makes automatic OpenAPI
generation and parameter injection fully work.

## Route parameters (slugs)

A path part written between braces is a slug, and its value is given to your callback

```php
Route::get('/products/{id}', [ProductController::class, 'read']);
```

You can constrain a slug with a type, written `{type:name}`

| Type | Matches |
|------|---------|
| `int` | `\d+` |
| `float` | `\d+(?:\.\d+)?` |
| `hex` | `[0-9a-fA-F]+` |
| `uuid` | a standard UUID |
| `date` | `YYYY-MM-DD` |
| `time` | `HH:MM:SS` |
| `datetime` | `YYYY-MM-DD HH:MM:SS` |
| `any` | `.*` (slashes included) |

Anything else is used as a raw regular expression, so `{[a-z]{2}:lang}` is a valid slug too.

Slug values are URL-decoded. A slug without a type never holds a `/` : `/products/a%2Fb` does not
match `/products/{id}`. Use `{any:name}` when a value may contain slashes.

Slug values are passed to your callback after the `Request`, in the order they appear in the path

```php
// Route::get('/agency/{int:agency}/user/{int:user}', [AgencyController::class, 'readUser'])
public static function readUser(Request $request, int $agency, int $user)
{
    // You can also read them from the request
    $request->getSlugValues(); // ['agency' => '5', 'user' => '12']
}
```

And you can rebuild a path from a route with `buildPath()`

```php
$route = Route::get('/product/{product}/price/{price}', fn () => null);
$route->buildPath([1, 2]); // /product/1/price/2
```

## Groups

`group()` applies a prefix, middlewares and extras to a set of routes, and groups can be nested

```php
$router->group('/api', [ApiKeyMiddleware::class], function: function (Router $router) {
    $router->addRoutes(
        Route::get('/ping', [PingController::class, 'ping']) // /api/ping
    );

    $router->group('/admin', [AdminMiddleware::class], routes: [
        Route::get('/users', [UserController::class, 'list']) // /api/admin/users
    ]);
});
```

The signature is `group(string $prefix, array $middlewares, array $extras, ?array $routes, ?callable $function, array $requires)`,
so use named arguments when you only need some of them. Prefixes are joined, middlewares and
extras are merged with the parent group's.

### Loading a routes file into a group

`require()` loads a PHP file into the group being declared, with `$router` in scope like any routes
file. `group()` takes the same files through `requires`. Paths are relative to your project root, and
a missing file throws an `InvalidArgumentException`.

```php
$router->group('/internal', [InternalTokenMiddleware::class], requires: ['App/Internal/routes.php']);

// same as
$router->group('/internal', [InternalTokenMiddleware::class], function: function (Router $router) {
    $router->require('App/Internal/routes.php');
});
```

Inside a group, `function` runs first, then `routes`, then `requires`. Files of the `Routes/` and
`Router/` directories are already loaded for you : keep the files you `require()` elsewhere, or their
routes are declared twice.

## Extras

Extras are free-form metadata attached to a route ; the framework never interprets them, your code
does

```php
Route::get('/reports', [ReportController::class, 'list'], extras: ['permissions' => ['report:read']]);

// Anywhere a Request is available
$permissions = $request->getRoute()->getExtras()['permissions'];
```

This is how `Route::file()` remembers the file to serve, and how `AuthenticationMiddleware`
carries the permissions a route requires.

## What your callback can return

Return a `Response` when you want control over the status, headers or body

```php
return Response::json(['id' => $product->id()], StatusCode::CREATED);
return Response::file($storage->path($name));
return Response::notFound();
```

Anything else is converted to a JSON response for you — a `Model`, a `Bunch`, an array, or any
nesting of those, is recursively converted with `toArray()`

```php
public static function list()
{
    return Product::select()->toBunch(); // 200, JSON array of products
}
```

A callback that returns nothing answers `204 No Content` with an empty body — use
`Response::json(null)` when you really mean to send a JSON `null`.

## Injected parameters

Route callbacks are resolved by the `Injector`, so you can ask for what you need

```php
public static function store(StoreProductRequest $request, Agency $agency, Logger $logger)
```

| Parameter type | What you get |
|----------------|--------------|
| `Request` | the incoming request |
| a `Request` subclass | the request, **already validated** — a `422` response is returned to the client if it isn't valid |
| a `Model` subclass | the model fetched from the slug value, or a `404` response if it does not exist |
| a component (`Logger`, `Database`, …) | its global instance |
| a `ConfigurationElement` | the resolved configuration element |
| any other class | a new instance, its own dependencies resolved too |

Validation lives in the request class itself

```php
class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            'name' => Param::string(true, false),
            'price_dollar' => Param::float(true),
            'managers' => Param::array(Param::object()),
        ];
    }
}

// In your callback
$request->validated();         // every validated (and coerced) value
$request->validated('name');   // one of them
$request->only(['name']);      // a subset
```

Models resolved from a slug are also available by name

```php
// Route::get('/agency/{agency}', [AgencyController::class, 'read'])
public static function read(Request $request, Agency $agency)
{
    $request->getSlugObject('agency'); // the same Agency instance
}
```

## Middlewares

A middleware is a class implementing `Middleware`, with a single static `handle()` method. Call
`$next($request)` to continue the chain, or return a `Response` to stop it

```php
class ApiKeyMiddleware implements Middleware
{
    public static function handle(Request $request, Closure $next): Request|Response
    {
        if ($request->getHeader('x-api-key') !== env('API_KEY'))
            return Response::unauthorized('Invalid API key');

        return $next($request);
    }
}
```

Middlewares are executed in declaration order : the ones given to the route first, then those of
each enclosing group from the innermost to the outermost, and finally the `commonMiddlewares` of
your `RouterConfiguration`.

### Guarding a group with permissions

`AuthenticationMiddleware` is a ready-made base class for permission checks : you implement how to
read the current user's permissions and what to answer when they are missing, then declare guarded
groups with `guard()`

```php
class Guard extends AuthenticationMiddleware
{
    public static function getUserPermission(): array
    {
        return MyUser::getInstance()->permissions();
    }

    public static function getErrorResponse(mixed $missingPermissions): Response
    {
        return Response::json(['missing' => $missingPermissions], StatusCode::FORBIDDEN);
    }
}

Guard::guard(['invoice:read'], function (Router $router) {
    $router->addRoutes(
        Route::get('/invoices', [InvoiceController::class, 'list'])
    );
});
```

Guards nest, and their permissions add up : a route inside `guard(['admin'])` and then
`guard(['invoice:read'])` needs both.

## Configuration

The router is configured with a `RouterConfiguration` element in your `cube.php`

```php
new RouterConfiguration(
    loadControllers: true,
    loadRoutesFiles: true,
    apis: [new StaticServer('FrontEnd/Public')],
    commonMiddlewares: [RememberMe::class],
    commonPrefix: '/',
)
```

| Option | Purpose |
|--------|---------|
| `loadControllers` | load routes declared by your `Controller` classes (default `true`) |
| `loadRoutesFiles` | load PHP files from your apps `Routes/` directories (default `true`) |
| `apis` | additional `WebAPI` services, as class names or instances |
| `commonMiddlewares` | middlewares applied to every route |
| `commonPrefix` | prefix applied to every route |
| `cached` | remember, in the `Cache` component, which route answers each method and path ; only routes whose callback is a `[Controller::class, 'method']` array are cached |

## Responses the router produces on its own

| Situation | Response |
|-----------|----------|
| no route matched the path | `404 Not Found` |
| the path matched but not the method | `405 Method Not Allowed`, listing the allowed methods |
| an `OPTIONS` request on a known path | `204 No Content` with the CORS headers of the allowed methods |
| an injected request was invalid | `422 Unprocessable Content` with the validation errors |
| an injected model was not found | `404 Not Found` |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./105-logging.md">Previous : Logging</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./107-controller-and-apis.md">Next : Controller and apis</a></div></td></tr></table>
