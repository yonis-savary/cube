<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./10-commands.md">Previous : Commands</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./12-requests-and-validation.md">Next : Requests and validation</a></div></td></tr></table>

# Routing and middlewares

The `Router` component turns a `Request` into a `Response` : it holds your routes, finds the one
matching the incoming request, runs its middlewares, then calls your callback with its parameters.

```
Request ---> Router ---> [ middlewares ] ---> your callback ---> Response
```

Here is a controller declaring two routes

```php
class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/products', [self::class, 'list']),
            Route::get('/products/{int:id}', [self::class, 'read']),
        );
    }

    public static function list(Request $request)
    {
        return Product::select()->fetchBunch(); // 200, a JSON array of products
    }

    public static function read(Request $request, Product $product)
    {
        return $product; // 404 when no product has this id
    }
}
```

What goes *inside* the callbacks — reading and validating input, building responses — is covered
by [Requests and validation](./12-requests-and-validation.md) and
[Controllers and responses](./13-controllers-and-responses.md).

## Declaring routes

Cube loads routes from three places, in this order

- every **controller**, through its `routes()` method — controllers are discovered, dropping the
  file in your application is enough
- every **routes file**, any PHP file inside your application's `Routes/` (or `Router/`) directory,
  where the `$router` variable is in scope
- every **`WebAPI` service** listed in the `apis` option of your `RouterConfiguration` (see
  [Claiming a request before routing](./13-controllers-and-responses.md#claiming-a-request-before-routing))

Here is a routes file

```php
// App/Routes/health.php
$router->addRoutes(
    Route::get('/health', [HealthController::class, 'check']),
);
```

`addRoutes()` accepts `Route` objects, `null` (handy for conditional routes) and callables
receiving the router, so you can split a big route set into functions

```php
$router->addRoutes(
    Route::get('/products', [ProductController::class, 'list']),
    $debug ? Route::get('/debug', [DebugController::class, 'dump']) : null,
    fn (Router $router) => $router->addRoutes(Route::get('/agencies', [AgencyController::class, 'list'])),
);
```

A route whose `[Class::class, 'method']` callback does not exist is refused when it is added, with
an `InvalidArgumentException` naming the class or the method.

When two routes match the same request, the first one declared wins.

### Route factories

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
| `new Route($path, $callback, ['PUT', 'PATCH'])` | the methods you list, an empty list meaning any |

Every factory takes the same two optional parameters : `$middlewares` and `$extras`

```php
Route::get('/invoices', [InvoiceController::class, 'list'], [ApiKeyMiddleware::class], ['scope' => 'billing']);
```

`Route::file()` takes an absolute path or a path relative to your project root. When the file does
not exist, the route logs an error and answers `404`.

A trailing slash does not matter : `/products/` and `/products` reach the same route, however it
was declared.

### Array callbacks or closures

A callback is either an array `[SomeClass::class, 'method']` or a closure. **Prefer the array
form** : the framework can reflect on it, which is what the [API documentation](./15-api-documentation.md)
and the route cache need, and it keeps your routes in your controllers.

```php
Route::get('/products', [ProductController::class, 'list']); // recommended
Route::get('/ping', fn () => 'pong');                        // fine for a one-liner, ignored by the API documentation
```

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

You can also read the raw values from the request

```php
// Route::get('/agency/{int:agency}/user/{int:user}', [AgencyController::class, 'readUser'])
$request->getSlugValues(); // ['agency' => '5', 'user' => '12']
```

And you can rebuild a path from a route with `buildPath()`, which takes the values in slug order
(a model gives its primary key)

```php
$route = Route::get('/product/{product}/price/{price}', [PriceController::class, 'read']);
$route->buildPath([1, 2]); // /product/1/price/2
```

## Callback parameters

Your callback's parameters are filled **by position**

1. the first one always receives the request
2. the next ones receive the slugs, in the order they appear in the path
3. the remaining ones are resolved by the `Injector`, like a constructor dependency (see
   [Dependency injection](./05-dependency-injection.md))

```php
// Route::put('/agency/{agency}/user/{int:user}', [AgencyController::class, 'updateUser'])
public static function updateUser(
    UpdateUserRequest $request, // 1. the request, validated against its rules
    Agency $agency,             // 2. the {agency} slug, turned into a model
    int $user,                  // 2. the {user} slug, as is
    Logger $logger,             // 3. resolved by the Injector
) {}
```

Since positions decide, the first parameter is the request even when you do not need it : keep it
and name it `$_` rather than removing it, or the first slug lands in the wrong parameter.

The type of a positional parameter changes what it receives

| Parameter | What you get |
|-----------|--------------|
| first, typed `Request` or untyped | the incoming request |
| first, typed with a `Request` subclass | the request, **already validated** — a `422` response is sent otherwise (see [Requests and validation](./12-requests-and-validation.md)) |
| a slug position, typed with a `Model` subclass | the model found by its primary key, or a `404` response |
| a slug position, scalar or untyped | the raw slug value |
| after the slugs | whatever the `Injector` resolves : a component's instance, a resolved `ConfigurationElement`, a new object built recursively, or the default value |

A model is fetched by its primary key only : whether the current user may see or change it is for
your code to decide, in the callback or in a middleware.

Once the callback is resolved, `getSlugObject()` gives back what each parameter received, by
parameter name

```php
// Route::get('/agency/{agency}', [AgencyController::class, 'read'])
public static function read(Request $request, Agency $agency)
{
    $request->getSlugObject('agency'); // the same Agency instance
}
```

## Groups

`group()` applies a prefix, middlewares and extras to a set of routes, and groups nest

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

The signature is `group(string $prefix = '/', array $middlewares = [], array $extras = [], ?array $routes = null, ?callable $function = null, array $requires = [])` :
use named arguments when you only need some of them. Prefixes are joined, middlewares and extras
are merged with the parent group's. The `function` callback receives the router and the
`RouteGroup` being declared.

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

// Anywhere the request is available, a middleware for example
$permissions = $request->getRoute()->getExtras()['permissions'];
```

This is how `Route::file()` remembers the file to serve, and how `AuthenticationMiddleware`
carries the permissions a route requires.

## Middlewares

A middleware is a class implementing `Middleware`, with a single static `handle()` method. Call
`$next($request)` to continue the chain, or return a `Response` to stop it

```php
class ApiKeyMiddleware implements Middleware
{
    public static function handle(Request $request, Closure $next): Request|Response
    {
        if (!ApiKey::findWhere(['token' => $request->getHeader('x-api-key')]))
            return Response::unauthorized('Invalid API key');

        return $next($request);
    }
}
```

`handle()` is static, so a middleware gets nothing injected : reach a component with
`Foo::getInstance()` or build a service with `inject(Foo::class)` inside it.

### Execution order

For a route declared inside groups, the chain runs

1. the middlewares given to the route itself
2. the `commonMiddlewares` of your `RouterConfiguration`
3. the middlewares of each enclosing group, from the outermost to the innermost

```php
// RouterConfiguration(commonMiddlewares: [RememberMe::class])
$router->group('/api', [ApiKeyMiddleware::class], function: function (Router $router) {
    $router->group('/admin', [AdminMiddleware::class], routes: [
        Route::get('/users', [UserController::class, 'list'], [AuditMiddleware::class]),
    ]);
});

// GET /api/admin/users : AuditMiddleware, RememberMe, ApiKeyMiddleware, AdminMiddleware, then the callback
```

### What a middleware sees

Middlewares run **before** the callback parameters are resolved, so a client your middleware refuses
never learns whether the slug model exists (`404`) or what the request rules expect (`422`). A
middleware therefore sees the request as it came in : `getSlugValues()` gives the raw slugs, but
there is no `Request` subclass, no `validated()` and no `getSlugObject()` yet. Load the model
yourself when a middleware needs it

```php
class InvoiceOwnerMiddleware implements Middleware
{
    public static function handle(Request $request, Closure $next): Request|Response
    {
        $invoice = Invoice::find($request->getSlugValues()['invoice'] ?? null);

        if ($invoice && $invoice->customer !== Authentication::getInstance()->userId())
            return Response::forbidden();

        return $next($request);
    }
}
```

Whatever the callback returns, a middleware receives it from `$next()` already converted to a
`Response`.

### Guarding a group with permissions

`AuthenticationMiddleware` is a ready-made base class for permission checks : you implement how to
read the current user's permissions and what to answer when some are missing, then declare guarded
groups with `guard()`

```php
class Guard extends AuthenticationMiddleware
{
    public static function getUserPermission(): array
    {
        return User::find(Authentication::getInstance()->userId())->permissions();
    }

    public static function getErrorResponse(mixed $missingPermissions): Response
    {
        return Response::json(['missing' => $missingPermissions], StatusCode::FORBIDDEN);
    }
}
```

Then, in a controller

```php
public function routes(Router $router): void
{
    Guard::guard(['invoice:read'], function (Router $router) {
        $router->addRoutes(
            Route::get('/invoices', [InvoiceController::class, 'list'])
        );
    }, $router);
}
```

`guard()` declares a group on `Router::getInstance()` unless you pass the router as its third
argument : pass the one you were given. Guards nest, and their permissions add up : a route inside
`guard(['admin'])` and then `guard(['invoice:read'])` needs both. `getErrorResponse()` receives the
list of the missing permissions.

## Security headers

Every response the router answers carries `X-Content-Type-Options: nosniff`. Declare the other
headers your application needs in `securityHeaders` : a callback that sets one of them keeps its own
value

```php
new RouterConfiguration(
    securityHeaders: [
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Content-Security-Policy' => "default-src 'self'",
    ],
)
```

Only send `Strict-Transport-Security` once your whole domain answers over HTTPS : browsers remember it.

## Cross-origin requests (CORS)

Cube sends no CORS header until you list the origins allowed to read your responses. With a
`CORSConfiguration` in your `cube.php`, the router answers the request's `Origin` when it is listed,
and nothing otherwise.

```php
new CORSConfiguration(
    allowedOrigins: ['https://shop.example.org', 'https://admin.example.org'],
    allowHeaders: 'Content-Type, Authorization',
    // lets the browser send cookies, never paired with '*'
    allowCredentials: true,
    maxAge: 86400,
)
```

When the origin is allowed, every response carries `Access-Control-Allow-Origin`,
`Access-Control-Allow-Methods` (`*`, or the methods of the path for an `OPTIONS` request),
`Access-Control-Allow-Headers` and `Access-Control-Max-Age`. With a list of origins, every response
also carries `Vary: Origin`, so a cache never serves one origin's answer to another.

A callback can set its own CORS headers with `Response::withCORSHeaders()` (see
[Controllers and responses](./13-controllers-and-responses.md#decorating-a-response)) : the router
then leaves them alone.

| Option | Default | Purpose |
|--------|---------|---------|
| `allowedOrigins` | `CORS_ALLOWED_ORIGINS` (comma separated), else none | full origins answered, `['*']` for any origin without credentials |
| `allowHeaders` | `CORS_ALLOWED_HEADERS`, else `Content-Type, x-requested-with` | request headers the browser may send |
| `allowCredentials` | `CORS_ALLOWED_CREDENTIALS`, else `false` | sends `Access-Control-Allow-Credentials: true`, ignored with `*` |
| `maxAge` | `86400` | seconds a browser may cache a preflight answer |

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

| Option | Default | Purpose |
|--------|---------|---------|
| `cached` | `false` | remember, in the `Cache` component, which route answers each method and path |
| `loadControllers` | `true` | load the routes declared by your `Controller` classes |
| `loadRoutesFiles` | `true` | load the PHP files of your applications' `Routes/` and `Router/` directories |
| `apis` | `[]` | additional `WebAPI` services, as class names or instances |
| `commonMiddlewares` | `[]` | middlewares applied to every route, and to what a `WebAPI` answers |
| `commonPrefix` | `/` | prefix applied to every route |
| `securityHeaders` | `[]` | headers added to every response that does not set them already |

With `cached`, only routes without slugs whose callback is a `[Controller::class, 'method']` array
are remembered. A remembered route is called directly : the `WebAPI` services are not asked to claim
the request first.

## Responses the router produces on its own

| Situation | Response |
|-----------|----------|
| no route matched the path | `404 Not Found`, empty body |
| the path matched but not the method | `405 Method Not Allowed`, a plain-text body listing the allowed methods |
| an `OPTIONS` request no route answers | `204 No Content`, with the CORS headers of the path's methods when the origin is allowed |
| the first parameter is a `Request` subclass and the request is invalid | `422 Unprocessable Content`, the validation errors as JSON |
| a slug parameter is a model and no row has that key | `404 Not Found` |
| a `ResponseException` escaped the callback | the response it carries |
| the callback returned nothing | `204 No Content` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./10-commands.md">Previous : Commands</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./12-requests-and-validation.md">Next : Requests and validation</a></div></td></tr></table>
