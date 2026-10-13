# Writing an application with Cube

This project is built on Cube, a back-end PHP 8 framework. This file tells you how to write
application code **with** Cube, and is meant to be enough on its own. It ships with the installed
version of the framework, so it matches the code in `vendor/yonis-savary/cube/src/`.

For a topic not covered here, the full documentation is at
https://github.com/yonis-savary/cube/tree/main/docs. When in doubt, read the source : Cube has no
magic, every behaviour is reachable from a typed class.

## Before writing anything

Cube already provides most plumbing. Use the framework tool before implementing your own :

| Need | Use |
|---|---|
| a route | `Route::get()`/`post()`/... declared in a `Controller` |
| validate input | a `Request` subclass with `getRules()` built from `Param` |
| a database table | a migration, then `php do models:generate` |
| a SQL query | `Model::select()`, `Model::find()`, `Query` — never a concatenated string |
| shape a model in a response | a `ModelResource` |
| fake models (tests, seeds) | `Model::factory()`, a `ModelFactory` when types are not enough |
| a CRUD API over a model | a `ModelAPI` |
| a collection pipeline | `Bunch` rather than `array_map`/`array_filter` chains |
| a shared service | the `Component` trait |
| build a class with its dependencies | `inject()` / `Injector` |
| every class of a kind | `Bunch::fromImplements()` / `fromExtends()` / `fromUses()` |
| one implementation per case | `Implementations::findOrFail()` |
| settings | a `ConfigurationElement` in `cube.php`, read with `::resolve()` |
| a CLI command | a `Command` subclass |
| logging | `Logger::getInstance()`, `Cube\info()`, `Cube\error()`... |
| react to something | `Event` + `EventDispatcher` |
| API documentation | `php do make:openapi`, plus OpenAPI attributes |

## Configuration

`cube.php` at the project root returns a list of typed configuration elements. Each subsystem owns
one element class : your IDE lists its options, there is no string key to guess.

```php
return [
    new Applications('App'),

    new DatabaseConfiguration(
        driver: 'mysql',
        database: env('DB_NAME'),
        host: env('DB_HOST'),
        port: (int) env('DB_PORT', 3306),
        user: env('DB_USER'),
        password: env('DB_PASSWORD'),
    ),

    new RouterConfiguration(
        commonMiddlewares: [RequestLogger::class],
        securityHeaders: ['X-Frame-Options' => 'DENY'],
    ),

    // Elements of another file, which returns an array of elements (a missing file only logs a warning)
    new Import('Config/production.php'),
];
```

A subsystem reads its element with `resolve()`, which returns the element given in `cube.php`,
else the default passed to `resolve()`, else `new static()` :

```php
$database = DatabaseConfiguration::resolve();
```

### Rules

- **One element per class.** A second `DatabaseConfiguration` replaces the first one, `Import`
  included.
- **No closure, no resource inside an element.** `php do configuration:cache` serializes them.
- **`Cube\env()` belongs in configuration files only.** Once cached, the configuration is no longer
  read from `cube.php`, so an `env()` call elsewhere would not see the same values.
- **After `configuration:cache`, `cube.php` is ignored** until you run `configuration:cache` again.
- An element holds ready-made collaborators (a cache driver, a session driver, an auth provider),
  never a string that someone must interpret later.

### Your own settings

Give your application settings their own element rather than reading `env()` here and there.
Every constructor parameter needs a default, since `resolve()` falls back to `new static()`

```php
class BillingConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $currency = 'EUR',
        public readonly float $vatRate = 0.2,
        public readonly ?string $stripeKey = null,
    ) {}
}

// cube.php
new BillingConfiguration(stripeKey: env('STRIPE_KEY')),

// anywhere
$vatRate = BillingConfiguration::resolve()->vatRate;
```

A class needing it can also receive it by injection : a constructor parameter typed
`BillingConfiguration` is filled with `BillingConfiguration::resolve()`.

`GenericElement` stores an untyped value under a name
(`new GenericElement('billing.currency', 'EUR')`, read with
`Configuration::getInstance()->resolveGeneric('billing.currency', 'USD')`). Prefer a typed element :
the IDE cannot help with a name.

### Built-in elements

| Element | Configures |
|---|---|
| `Applications(...$paths)` | the application directories to load |
| `AutoloaderConfiguration(cached:, displayErrors:)` | class index caching (on in production) and error display (on in debug) |
| `DatabaseConfiguration(driver:, database:, host:, port:, user:, password:)` | the SQL connection (`sqlite`, `mysql`, `pgsql`) |
| `MigrationManagerConfiguration(tableName:, directoryName:)` | the migrations table and directory |
| `RouterConfiguration(cached:, loadControllers:, loadRoutesFiles:, apis:, commonMiddlewares:, commonPrefix:, securityHeaders:)` | routing, global middlewares, extra `WebAPI`s such as a `StaticServer` |
| `CORSConfiguration(allowedOrigins:, allowHeaders:, allowCredentials:, maxAge:)` | CORS answers |
| `CacheConfiguration(driver)` | the default `Cache` driver |
| `SessionConfiguration(namespace:, driver:)` | the session driver |
| `AuthenticationConfiguration(provider)` | the `Authentication` provider |
| `UserRegisterConfiguration(...)` | the remember-me cookie |
| `OpenAPIConfiguration(outputFile:, title:, version:, authenticationScheme:)` | the generated OpenAPI document |
| `WebsocketConfiguration(...)`, `BroadcastConfiguration(...)` | the websocket server and its broadcast client |
| `LargeUploadManagerConfiguration(storageName:, maxSize:)` | chunked uploads |
| `ToTypesConfiguration(outputFile:)` | `php do models:to-types` output |

### Environment

The `.env` file at the project root is read by `Cube\env('KEY', $default)`. Process variables are
read first and `.env` overrides them. `true`/`on`/`yes` read as `"1"`, `false`/`off`/`no`/`none`/`null`
as `""`, and values need no quotes.

| `.env` key | Role |
|---|---|
| `env` | the environment : starting with `prod` makes `Cube\isProduction()` true, `debug`/`dev`/`local`/`test` make `Cube\isDebug()` true |
| `APP_KEY` | signs cache and queue payloads, keys `Cube\encrypt()` : 32 bytes minimum, `php do key:generate` prints one |

In production the class index is cached : after each deployment, run
`php do cube:dispatch --event PostDeployment` and restart php-fpm.

## Adding a file is enough

Cube registers classes by discovering them, never by listing them. A new controller, command,
migration, factory, queue or channel is **one new file** inside an application directory declared
in `cube.php` (`new Applications('App')`). Do not look for a registry, a service provider or a
routes array to update : there is none.

Discovery derives class names from composer's mapping : the application directory must be declared
in `composer.json` (`"autoload": {"psr-4": {"App\\": "App/"}}`).

Discovery only explores Cube and the application directories. A class elsewhere — a shared `src/`,
`tests/`, a vendor package — is not found unless a `Requires/` file adds its directory, which must be
autoloaded by composer

```php
// App/Requires/discovery.php
Autoloader::addToExploreMap('vendor/acme/shop-controllers/src');
```

Some directory names carry meaning inside an application :

| Directory | Content |
|---|---|
| `Routes/`, `Router/` | route files, `$router` is in scope |
| `Requires/`, `Includes/`, `Helpers/`, `Schedules/`, `Cron/` | PHP files included at boot |
| `Views/` | plain-PHP views for `Cube\render()`, escape every value with `Cube\e()` |
| `Assets/` | files served by the asset server |
| `Migrations/` | migration files |

Everything else (`Models/`, `Controllers/`, `Commands/`, `Resources/`, `Factories/`...) is free :
classes are found by type, not by path.

## Discovery and strategies

Use the same tools as the framework to keep "adding a case = adding a file".

**Every class of a kind** — build all of them

```php
$widgets = Bunch::fromImplements(DashboardWidget::class)
    ->map(fn (DashboardWidget $widget) => $widget->render());
```

`fromImplements()`, `fromExtends()`, `fromUses()` build each class found through the `Injector`.
`Autoloader::classesThatImplements()` and siblings give the class names without building them.

**One implementation per case** — each class says whether it supports the case, with a `static`
method so only the chosen class is built

```php
interface InvoiceExporter
{
    public static function supports(string $format): bool;
    public function export(Invoice $invoice): string;
}

class CsvExporter implements InvoiceExporter
{
    public static function supports(string $format): bool
    {
        return 'csv' === $format;
    }
    // ...
}

$exporter = Implementations::findOrFail(
    InvoiceExporter::class,
    fn (string $class) => $class::supports($format),
    for: $format,
);
```

| Method | When no class supports the case |
|---|---|
| `findOrFail($base, $supports, $args, for:)` | throws an `ImplementationNotFoundException` listing every candidate |
| `find($base, $supports, $args)` | returns `null` |
| `findAll($base, $supports, $args)` | returns an empty `Bunch` (otherwise every match) |

Never write a `match` or an array mapping a key to a class : that is the registry this replaces.

## Dependency injection

Cube builds your classes through the `Injector`, which reads constructor types and fills them. Ask
for what you need in the constructor : never call `new` on a service, never reach for a global.

```php
class InvoiceService
{
    public function __construct(
        protected Database $database,               // a Component : its current instance
        protected BillingConfiguration $billing,    // a ConfigurationElement : resolve()
        protected PdfRenderer $renderer,            // any other class : built the same way, recursively
        protected PaymentGateway $gateway,          // an interface : bound with provide(), see below
        protected int $retries = 3,                 // a scalar : its default value
    ) {}
}
```

### Where injection happens

| Class | Injected |
|---|---|
| `Controller` | the constructor, when routes are loaded and again on each call of an instance-method route |
| route callback | its parameters, after the request and the slugs (see [Routes and controllers](#routes-and-controllers)) |
| `Command` | the constructor (`execute(Args $args)` itself receives only the arguments) |
| `Component` | the constructor, when `getInstance()` builds the default instance |
| `Queue`, `Channel`, `ModelResource`, `ModelFactory` | the constructor |
| a class found by `Bunch::fromImplements()`/`fromExtends()`/`fromUses()` or `Implementations` | the constructor |
| anything you build with `inject(Foo::class)` / `Injector::getInstance()->instanciate(Foo::class)` | the constructor (always a **new** instance, even for a `Component` : use `getInstance()` for the shared one) |
| `Middleware` | **nothing** : `handle()` is static, use `Foo::getInstance()` or `inject(Foo::class)` inside it |

A command, then

```php
class SendInvoices extends Command
{
    public function __construct(
        protected InvoiceService $invoices,
    ) {}

    public function execute(Args $args): int
    {
        $this->invoices->sendPending();
        return 0;
    }
}
```

### Resolution order

Each constructor parameter is resolved by the first rule that applies :

| Parameter | Resolved as |
|---|---|
| a value passed explicitly (`inject(Foo::class, [$database])`) | that value, filling parameters **by position** |
| a type bound with `provide()` | the bound value |
| a class using `Component` | its `getInstance()` |
| a `ConfigurationElement` | its `resolve()` |
| any other class | built by the `Injector`, recursively |
| a variadic typed with a class or an interface (`Widget ...$widgets`) | every discovered subclass or implementation, built |
| anything else (scalar, union type, unbound interface) | the default value, or an `InvalidArgumentException` |

A nullable class parameter (`?Database $database = null`) is still resolved, not left `null` : in a
constructor, that is how you receive the component.

### Binding an interface

An interface cannot be built : bind it from a `Requires/` file. A class name is resolved like a
parameter typed with it, an object is given as is

```php
// App/Requires/bindings.php
Injector::getInstance()->provide(PaymentGateway::class, StripeGateway::class);
```

A closure decides at runtime. It receives the class asking for the dependency, so two consumers can
get two implementations

```php
Injector::getInstance()->provide(
    StorageDriver::class,
    fn (?string $requester) => $requester === ArchiveService::class ? new ColdStorage() : new LocalStorage()
);
```

`provideVariadic()` does the same for a variadic parameter. The provided value must extend or
implement the bound type, otherwise the `Injector` throws a `RuntimeException`.

### Components

A class using the `Component` trait is a replaceable singleton. Make one for a service with state
that the whole request shares (a client with an open connection, a registry built once)

```php
class ExchangeRates
{
    use Component;

    public function __construct(
        protected HttpClient $client,   // injected when getInstance() builds it
    ) {}
}

ExchangeRates::getInstance();                          // the shared instance
ExchangeRates::setInstance($rates);                    // replace it
$rates->asGlobalInstance(fn () => $this->convert());   // replace it during a callback only
```

Override `getDefaultInstance()` only when the default instance needs something the `Injector`
cannot give. A stateless service needs no `Component` : constructor injection is enough.

Outside a constructor, a method that uses a component takes it as a nullable last parameter, so a
caller or a test can pass another one

```php
public function convert(float $amount, ?ExchangeRates $rates = null): float
{
    $rates ??= ExchangeRates::getInstance();
    // ...
}
```

## Commands

A command is a `Command` subclass implementing `execute(Args $args): int`. Its identifier is
derived from its class : `App\Commands\CountProducts` answers `php do app:count-products`.
`php do cube:help` lists every available command.

| Command | Does |
|---|---|
| `php do make:migration Name` | creates a migration file |
| `php do migrate:migrate` | runs the pending migrations |
| `php do models:generate` | regenerates the models from the database |
| `php do models:to-types` | writes TypeScript types for the models |
| `php do make:openapi` | writes the OpenAPI document |
| `php do configuration:cache` | caches `cube.php` |
| `php do cache:clear` | empties the cache |
| `php do key:generate` | prints a new `APP_KEY` |

## Routes and controllers

A controller declares its own routes. It is built by the `Injector`, so its constructor can receive
any dependency. Here is an example

```php
class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products
    ) {}

    public function routes(Router $router): void
    {
        $router->group('/product', middlewares: [MustBeLogged::class], routes: [
            Route::post('/', [self::class, 'store']),
            Route::get('/{product}', [self::class, 'show']),
        ]);
    }

    // The request is validated before this body runs : a failure answers 422
    public function store(StoreProductRequest $request): Product
    {
        return $this->products->create($request->validated());
    }

    // The slug is turned into Product::find() : a missing row answers 404
    public function show(Request $request, Product $product): Product
    {
        return $product;
    }
}
```

- Use `[Controller::class, 'method']` callbacks rather than closures : Cube reflects on them for
  injection and OpenAPI generation, and skips closures in the document.
- Callback parameters are filled **by position** : the first one always receives the request, the
  next ones the slugs in the order of the path, the remaining ones are built by the `Injector`.
  Typed as a `Request` subclass, the request is validated. Typed as a `Model`, a slug is fetched.
- Type the return : a `Model`, a `Bunch` or an array produces a JSON response, and the return type
  documents the OpenAPI response. Return a `Response` (`Response::json($product, StatusCode::CREATED)`...) only to
  choose the status code or headers.
- A middleware implements `Middleware::handle(Request $request, Closure $next): Request|Response`.
- Abort from deep inside by throwing a `ResponseException` carrying the `Response`.

## Validating input

Declare the expected shape once, in a `Request` subclass. Here is an example

```php
class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            'name' => Param::string(),
            'price_dollar' => Param::float(nullable: true)->optional(),
            'managers' => Param::array(Param::object(['manager' => Param::string()])),
        ];
    }
}
```

Read the coerced values with `$request->validated()` (or `validated('name')`). The controller never
re-checks what the rules already guarantee. Use named arguments on `Param` factories : their
boolean parameters are unreadable otherwise.

## Shaping responses with resources

A model returned as is leaves the application whole : every field and every loaded relation, a
password hash included. A `ModelResource` decides what a model looks like, and you apply it
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
            'agency' => AgencyResource::of($model->agency), // a model left in the array is answered whole
        ];
    }
}

return UserResource::of($user);   // one model
return UserResource::of($users);  // an array or a Bunch of models
```

A model can have as many resources as it has representations : nothing picks one for you. Answer
every model that holds sensitive fields through a resource.

## OpenAPI documentation

`php do make:openapi` writes the OpenAPI 3.1 document from your routes. Most of it comes from your
code, with no attribute :

| Your code | The document |
|---|---|
| a slug `{product}` / `{int:id}` | a path parameter, typed from the callback argument or the slug type |
| a `Request` subclass on `GET`/`DELETE` | query parameters, one per rule |
| a `Request` subclass on `POST`/`PUT`/`PATCH` | a JSON body, plus a `422` response |
| a slug received as a model | a `404` response |
| a model return type (`: Product`, `: ?Product`) | a `200` response with the model schema (plus `204` when nullable) |
| `: void` | a `204` response |

Add an attribute only for what the code cannot say :

```php
#[Endpoint('List products', 'Every product of the catalog, filtered by name')]
#[ResourceResponse(ProductResource::class, isArray: true)]
public function read(SearchProductRequest $request): array

#[ModelResponse(Product::class, responseCode: StatusCode::CREATED, description: 'The created product')]
public function store(StoreProductRequest $request): Response

#[RawResponse(['total' => 120, 'pages' => [1, 2, 3]])]
public function stats(): array

#[Hidden] // on a method or a whole controller
public function healthCheck(): Response
```

| Attribute | Use it when |
|---|---|
| `#[Endpoint(summary, description, operationId)]` | always on a public route : the default summary is the method name |
| `#[ResourceResponse(Resource::class, isArray:, responseCode:)]` | the route answers through a `ModelResource` (shape read from a factory-made model, relations excluded) |
| `#[ModelResponse(Model::class, isArray:, responseCode:)]` | the route answers a model the return type cannot express (`array`, `Response`) |
| `#[RawResponse(data, file:, responseCode:)]` | any other shape, from an example value or a JSON file |
| `#[Hidden]` | the route must stay out of the document |

`operationId` must be unique in the whole document : one route per verb, and distinct method names
or `#[Endpoint]` summaries. Declare the title, version and authentication scheme in
`OpenAPIConfiguration`.

## Database and models

1. Write a migration with `php do make:migration CreateProductTable`, against the `Plan` API and
   `ModelField` (raw SQL only when the `Plan` cannot express it).
2. Run it with `php do migrate:migrate`.
3. Regenerate the models from the database with `php do models:generate`.

```php
return new class extends Migration
{
    public function up(Plan $plan, Database $database)
    {
        $plan->create('product', [
            ModelField::id(),
            ModelField::string('name', 200)->notNull()->unique(),
            ModelField::decimal('price_dollar', 10, 5)->nullable(),
        ]);
    }

    public function down(Plan $plan, Database $database)
    {
        $plan->dropTable('product');
    }
};
```

Generated members carry the `#[Generated]` attribute and are rewritten on each generation. Add your
own methods **without** that attribute : they are preserved. Never hand-edit a `#[Generated]`
member : change the migration and regenerate.

Query through the model, never with a SQL string

```php
$product = Product::find($id);
$cheapProducts = Product::select()->where('price_dollar', 10, '<')->fetch();
$product = Product::fromArray(['name' => 'screen'])->save();
```

## CRUD with ModelAPI

`ModelAPI` does the CRUD work over one model. It declares no route : your controller declares them
and delegates

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

    protected function getModelResource(): ?ModelResource
    {
        return new ProductResource();
    }
}

// in a controller receiving ProductModelAPI $products by constructor
public function read(SearchProductRequest $request): array
{
    return $this->products->readItems($request);
}
```

| Method | Does |
|---|---|
| `createItems(Request)` | creates one row, or several from a list, answers `201` |
| `readItems(Request)` | reads rows filtered by the validated fields |
| `updateItem(Model, Request)` | updates the row with the validated fields, primary key excepted |
| `deleteItem(Model)` | deletes the row |

Always give a `Request` subclass : its rules decide which columns a client may write and filter on.
A plain `Request` is refused by the write methods.

## Code style expected in a Cube application

- **Type everything** and keep the IDE informed : `@template`, `class-string<T>`,
  `@return static`, `Bunch<int,Product>`, `@extends ModelResource<User>`. No `__call`, no
  string-keyed lookups.
- **Guard clauses**, no `else` after a `return` or `throw`, `??=` for lazy defaults, `match`
  over `if`/`elseif` ladders.
- **Invalid user input is not an exception** : it is a validation result. Exceptions are for
  programming errors and missing configuration.
- **Log with templates and context**, never concatenation :
  `Logger::getInstance()->warning('Cannot sync {product}', ['product' => $product->id])`.
- **Never edit `vendor/`** : extend a framework class or bind your own implementation instead.

## Tests

Extend `Cube\Test\CubeTestCase` to drive the application over HTTP with a fresh database per test

```php
class ProductTest extends CubeTestCase
{
    public function testStoreProduct()
    {
        $this->post('/product')->assertUnprocessableContent();
        $this->postJson('/product', ['name' => 'my-product'])->assertOk();
    }
}
```

Build data with factories (`Product::factory()->create()`). Use a plain
`PHPUnit\Framework\TestCase` for code that needs no application, and swap a component with
`$instance->asGlobalInstance(fn () => ...)` instead of mocking it.

## Faking models with factories

`Model::factory()` builds models filled with fake values (Faker), for tests, seeds or examples

```php
$user = User::factory()->make();                          // not saved
$user = User::factory()->create(['login' => 'alice']);    // saved, given values win
$users = User::factory()->createMany(10, ['agency' => $agency->id()]);
$user = User::factory()->withSeed(42)->make();            // same seed, same values
```

Every field gets a value of its type. A foreign key gets a row created through the referenced
model's own factory. Write a factory only for fields a type alone cannot fake well — it is found by
discovery

```php
/**
 * @extends ModelFactory<User>
 */
class UserFactory extends ModelFactory
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    protected function definition(Generator $faker): array
    {
        return [
            'login' => $faker->unique()->email(),
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ];
    }
}
```
