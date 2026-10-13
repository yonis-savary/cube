<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./05-dependency-injection.md">Previous : Dependency injection</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./07-collections.md">Next : Collections</a></div></td></tr></table>

# Components

A component is a class using the `Component` trait : it has one shared instance per class, built on
first use and replaceable at any time. `Database`, `Logger`, `Router` and most Cube services are
components.

```php
class ExchangeRates
{
    use Component;

    public function __construct(
        protected HttpClient $client,   // injected when getInstance() builds the default instance
    ) {}

    public function convert(float $amount, string $from, string $to): float
    {
        // ...
    }
}

ExchangeRates::getInstance()->convert(100, 'EUR', 'USD'); // built on the first call, shared afterwards
```

## Building the default instance

The first `getInstance()` call asks `getDefaultInstance()` for the instance, then keeps it. By default
`getDefaultInstance()` builds the class through the [`Injector`](./05-dependency-injection.md), so the
constructor can ask for its dependencies.

Override it when the default instance needs something the `Injector` cannot give, a file name or a
value read from the configuration

```php
class AuditLog
{
    use Component;

    public static function getDefaultInstance(): static
    {
        return new static(Logger::forFile('audit.csv'));
    }

    public function __construct(
        protected Logger $logger
    ) {}
}
```

## Replacing the instance

Every component can be swapped, which is what makes them easy to test and to scope

```php
$rates = new ExchangeRates(new HttpClient());

ExchangeRates::setInstance($rates);        // replaces the shared instance from now on
ExchangeRates::hasInstance();              // true when an instance is already built or set
ExchangeRates::removeInstance();           // the next getInstance() builds a new default instance
```

To replace it during a callback only, use `withInstance()`, or `asGlobalInstance()` on the instance
itself. The previous instance comes back afterwards, even if the callback throws, and the callback's
return value is handed back

```php
$total = ExchangeRates::withInstance($rates, fn (ExchangeRates $rates) => $cart->total());

// the same, from the instance
$total = $rates->asGlobalInstance(fn () => $cart->total());
```

Here is an example sending the logs of one task to their own file

```php
$logger = Logger::forFile('invoices.csv');

$logger->asGlobalInstance(function () use ($invoices) {
    // Logger::getInstance() is $logger here, for this code and everything it calls
    $invoices->sendPending();
});
```

The instance belongs to the class that is asked : `NullLogger::getInstance()` and
`Logger::getInstance()` are two different slots, so replace the one your code reads.

## Component or plain service

Make a class a component when it holds **state the whole process shares** : an open connection, a
registry built once, the current user. A stateless service needs no `Component` : asking for it in a
constructor is enough, the `Injector` builds it.

| Your class | Make it |
|---|---|
| holds a connection, a cache, a file handle | a component |
| is expensive to build and the same for everyone | a component |
| computes things from what it is given | a plain class, injected |

## Passing a component to a method

In a constructor, type the component and the `Injector` gives you its current instance. Outside a
constructor, a method that uses a component takes it as a nullable last parameter, resolved at the
top of the body, so a caller or a test can pass another one

```php
public function convert(float $amount, string $from, string $to, ?ExchangeRates $rates = null): float
{
    $rates ??= ExchangeRates::getInstance();
    // ...
}
```

Cube follows this pattern everywhere : `$product->save($database)`, `Product::select()->fetch($database)`
and so on fall back on the shared instance when you give nothing.

## Running code with fresh components

`RequestContext::oneShot()` runs a callback where every component starts from scratch, then gives
each component back the instance it had before. Components created inside the callback do not
survive it.

```php
$database = Database::getInstance();

$report = RequestContext::oneShot(function (RequestContext $context) {
    // Database::getInstance() builds a new instance here
    return Product::select()->fetch();
});

Database::getInstance(); // $database again
```

The second parameter lists the components the callback shares with the outside, untouched

```php
RequestContext::oneShot(fn () => $this->handle($message), [Configuration::class, Logger::class]);
```

The callback receives the context, which is also the global `RequestContext` while it runs. It returns
whatever the callback returns.

`oneShot()` scans your classes to know every component each time it is called. To run many callbacks
with the same persistent components, build the context once and call `run()` on it

```php
$context = new RequestContext([Configuration::class, Logger::class, Injector::class]);

foreach ($messages as $message)
    $context->run(fn () => $this->handle($message));
```

The `Injector` is a component too : list it among the persistent components, otherwise the callback
gets a new `Injector` without the bindings you declared with `provide()`. Static properties are not
components : they are left as they are.

This is how the [unix socket server](./31-unix-socket-server.md) isolates its requests.

## Framework components

| Component | Holds |
|---|---|
| `Configuration` | the configuration elements of `cube.php` ([Configuration](./03-configuration.md)) |
| `Environment` | the values of `.env` |
| `Injector` | the bindings used to build classes ([Dependency injection](./05-dependency-injection.md)) |
| `Events` | the application-wide event dispatcher ([Events](./08-events.md)) |
| `Logger` | the default log file, `Storage/Logs/cube.csv` ([Logging](./09-logging.md)) |
| `Router` | the routes ([Routing and middlewares](./11-routing-and-middlewares.md)) |
| `Renderer`, `AssetsInserter` | the views and assets ([Views and assets](./14-views-and-assets.md)) |
| `OpenAPIGenerator` | the OpenAPI document generator ([API documentation](./15-api-documentation.md)) |
| `Database` | the connection described by `DatabaseConfiguration` ([Database and queries](./16-database-and-queries.md)) |
| `MigrationManager` | the migrations ([Migrations](./17-migrations.md)) |
| `ModelGenerator` | the model generation ([Models](./18-models.md)) |
| `ModelAPIGenerator` | the `ModelAPI` generation ([Model APIs](./19-model-apis.md)) |
| `Storage`, `Cache` | the `Storage` directory and the cache ([Storage and caching](./21-storage-and-caching.md)) |
| `Session` | the session ([Sessions](./22-sessions.md)) |
| `Authentication` | the authenticated user ([Authentication](./23-authentication.md)) |
| `RememberMe` | the remember-me tokens ([Remember me](./25-remember-me.md)) |
| `Scheduler` | the scheduled tasks ([Schedules](./28-schedules.md)) |
| `Websocket`, `Broadcast` | the websocket server and its client ([Websockets](./30-websockets.md)) |
| `LargeUploadManager` | the chunked uploads ([Controllers and responses](./13-controllers-and-responses.md)) |
| `MockServers`, `TestContext` | the test doubles ([Testing](./33-testing.md)) |
| `RequestContext` | the context of `RequestContext::run()` |

`UserComponent` is a variant whose instance is tied to the authenticated user, see
[User component](./24-user-component.md).

## Reference

| Method | Purpose |
|---|---|
| `getInstance()` | the shared instance, built with `getDefaultInstance()` on first call |
| `getDefaultInstance()` | builds the default instance ; through the `Injector` unless overridden |
| `setInstance($instance)` | replaces the shared instance |
| `hasInstance()` | whether an instance is built or set |
| `removeInstance()` | forgets the instance, the next `getInstance()` builds a new one |
| `withInstance($instance, $callback)` | replaces the instance while `$callback` runs, returns its result |
| `$instance->asGlobalInstance($callback)` | the same, called on the instance |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./05-dependency-injection.md">Previous : Dependency injection</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./07-collections.md">Next : Collections</a></div></td></tr></table>
