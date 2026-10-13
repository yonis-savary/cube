<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./04-applications-and-discovery.md">Previous : Applications and discovery</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./06-components.md">Next : Components</a></div></td></tr></table>

# Dependency injection

The `Injector` component builds your classes for you : it reads the types of a constructor (or of a
callback) and gives each parameter a value. Ask for what you need in the constructor rather than
calling `new` on a service or reaching for a global.

```php
class InvoiceService
{
    public function __construct(
        protected Database $database,               // a component : its current instance
        protected BillingConfiguration $billing,    // a ConfigurationElement : its resolve()
        protected PdfRenderer $renderer,            // any other class : built the same way, recursively
        protected PaymentGateway $gateway,          // an interface : bound with provide(), see below
        protected int $retries = 3,                 // a scalar : its default value
    ) {}
}

$invoices = inject(InvoiceService::class);
$invoices = Injector::getInstance()->instanciate(InvoiceService::class); // the same
```

## Where injection happens

Most of the classes Cube builds for you go through the `Injector`, so their constructor can ask for
dependencies

| Class | What is injected |
|---|---|
| `Controller` | the constructor, when routes are loaded, and again on each call of a route whose callback is an instance method |
| route callback | its parameters, after the request and the slugs (see [Routing and middlewares](./11-routing-and-middlewares.md)) |
| `Command` | the constructor ; `execute(Args $args)` itself only receives the arguments |
| a [component](./06-components.md) | the constructor, when `getInstance()` builds the default instance |
| `Queue` | the constructor, on `YourQueue::queue(...)` and in `php do cube:queue` |
| websocket `Channel` | the constructor, when the websocket server starts |
| `ModelResource` | the constructor, on each `YourResource::of(...)` |
| `ModelFactory` | the constructor, on `ModelFactory::for(...)` / `Model::factory()` |
| a class found by `Bunch::fromImplements()`, `fromExtends()`, `fromUses()` or `Implementations` | the constructor (see [Applications and discovery](./04-applications-and-discovery.md)) |
| anything you build with `inject()` / `Injector::getInstance()->instanciate()` | the constructor |

Two places are **not** injected

| Class | Why | What to do |
|---|---|---|
| `Middleware` | `handle()` is static, nothing is ever built | call `Foo::getInstance()` or `inject(Foo::class)` inside `handle()` |
| a `WebAPI` given by class name in `RouterConfiguration(apis:)` | built with `new`, without arguments | give an instance instead, or keep its constructor empty |

Here is a command receiving a service

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

And a controller doing the same : the router builds it once to read its `routes()`, then builds a
fresh one for each call of `read()`, because `read()` is not static

```php
class ProductController extends Controller
{
    public function __construct(
        protected ProductRepository $products,
    ) {}

    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/products/{int:id}', [self::class, 'read']),
        );
    }

    public function read(Request $request, int $id): Product
    {
        return $this->products->findOrFail($id);
    }
}
```

A static callback (`public static function read(...)`) is called without building the controller.

## Resolution order

Each parameter is resolved by the first rule that applies

| Parameter | Resolved as |
|---|---|
| a value passed explicitly (`inject(Foo::class, [$database])`) | that value, filling the parameters **by position** |
| a type bound with `provide()` | the bound value (see [Binding an interface](#binding-an-interface)) |
| a class using `Component` | its `getInstance()` |
| a `ConfigurationElement` | its `resolve()` |
| any other class | built by the `Injector`, recursively |
| a variadic typed with a class or an interface (`Widget ...$widgets`) | every discovered subclass or implementation, built (see [Variadic parameters](#variadic-parameters)) |
| anything else (scalar, union type, unbound interface) | the default value, or an `InvalidArgumentException` |

A few consequences worth knowing

- A nullable class parameter (`?Database $database = null`) is still resolved, not left `null` : in a
  constructor, that is how you receive the component.
- A union type (`Database|Cache $store`) is never resolved : it gets its default value, or the
  `Injector` throws.
- An interface or an abstract class cannot be built : bind it with `provide()`, otherwise an
  interface falls back on its default value and an abstract class fails when PHP tries to build it.
- Explicit values are matched by position only : `inject(InvoiceService::class, ['database' => $db])`
  ignores the `database` key. Pass a list.

Explicit values are what the router uses to hand a route callback its request and its slugs : a
`Request` subclass given that way is rebuilt and validated, and a `Model` parameter is fetched from
the value. [Routing and middlewares](./11-routing-and-middlewares.md) details it.

## Building a class yourself

`inject()` is a global function, a shortcut for `Injector::getInstance()->instanciate()`. The
second parameter holds the explicit constructor values

```php
$mailer = inject(InvoiceMailer::class);
$mailer = inject(InvoiceMailer::class, [$customDatabase]); // first parameter given, the others resolved
```

`inject()` always builds a new object, even for a component : `inject(Database::class)` opens a new
connection. Call `Database::getInstance()` when you want the shared one.

When the class itself is bound with `provide()`, `inject()` gives the bound value, so
`inject(PaymentGateway::class)` works once the interface is bound.

## Binding an interface

An interface cannot be built, so tell the `Injector` what to give for it. Do it from a `Requires/`
file of your application, so it runs at boot

```php
// App/Requires/bindings.php
Injector::getInstance()->provide(PaymentGateway::class, StripeGateway::class);
```

`provide()` accepts three kinds of value

| Value | Gives |
|---|---|
| a class name (`StripeGateway::class`) | that class resolved like a parameter typed with it : a component gives its `getInstance()`, a `ConfigurationElement` its `resolve()`, anything else is built |
| an object | that very object, every time |
| a closure | whatever the closure returns, computed each time a parameter needs it |

A closure receives the class asking for the dependency, so two consumers can get two
implementations

```php
Injector::getInstance()->provide(
    StorageDriver::class,
    fn (?string $requester) => $requester === ArchiveService::class ? new ColdStorage() : new LocalStorage()
);
```

The requester is the class whose constructor or method is being resolved. It is `null` when nothing
requested it : a closure route callback, or a direct `inject(StorageDriver::class)`. A closure may also
return a class name, resolved as above, or `null` to let the normal resolution continue.

The value must be an object that extends, implements or uses the bound type, otherwise the `Injector`
throws a `RuntimeException` naming both types.

A binding is not limited to interfaces : `provide()` on a class replaces it wherever it is injected.

### Bindings Cube declares

| Type | Bound to |
|---|---|
| `Psr\Log\LoggerInterface` | `Logger`, so its current instance |
| `EventDispatcher` | `Events`, so its current instance |

## Variadic parameters

A variadic parameter typed with an interface or a class receives every discovered implementation or
subclass, each one built by the `Injector`. Here is an example

```php
class DashboardController extends Controller
{
    /** @var DashboardWidget[] */
    protected array $widgets;

    public function __construct(
        DashboardWidget ...$widgets // every class implementing DashboardWidget
    ) {
        $this->widgets = $widgets;
    }
}
```

Adding a widget is adding a class : nothing lists them. Abstract classes are left out, see
[Registration by discovery](./04-applications-and-discovery.md#registration-by-discovery).

`provideVariadic()` replaces the discovered list with your own. Each value is resolved like a
`provide()` value : a class name is built, an object is given as is, and a closure receives the
requester class

```php
// App/Requires/bindings.php
Injector::getInstance()->provideVariadic(DashboardWidget::class, [SalesWidget::class, StockWidget::class]);
```

Every value must extend or implement the type, otherwise the `Injector` throws a
`RuntimeException`. A variadic typed with anything else than a single class or interface throws a
`RuntimeException` too, and explicit constructor arguments never fill a variadic parameter.

## Reference

| Method | Purpose |
|---|---|
| `inject($class, $args = [])` | build `$class`, global shortcut of `instanciate()` |
| `Injector::getInstance()->instanciate($class, $args = [])` | build `$class`, `$args` filling the first constructor parameters |
| `->provide($type, $value)` | bind a type to a class name, an object or a closure |
| `->provideVariadic($type, $values)` | bind the list given to variadic parameters of that type |
| `->getDependencies($callback, $args = [])` | the resolved arguments of a callable or a `[Class::class, 'method']` array, without calling it |

Bindings belong to an `Injector` instance : a new `Injector` starts without any, the framework's
included. [`RequestContext::run()`](./06-components.md#running-code-with-fresh-components) never
resets the `Injector`, so your bindings stay available inside it.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./04-applications-and-discovery.md">Previous : Applications and discovery</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./06-components.md">Next : Components</a></div></td></tr></table>
