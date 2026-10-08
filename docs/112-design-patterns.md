<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./111-models.md">Previous : Models</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./201-static-server.md">Next : Static server</a></div></td></tr></table>

# Design patterns

Cube is built on a handful of design patterns, and exposes the tools behind them so your application
can use the same ones : this page maps each pattern to the component that implements it.

| Pattern | Use it when | Cube tool |
|---|---|---|
| Strategy / driver | one implementation per case, picked at runtime | `Implementations::findOrFail()` |
| Registration by discovery | every class of a kind must be collected | `Bunch::fromImplements()`, `fromExtends()`, `fromUses()` |
| Dependency injection | a class needs collaborators it should not build itself | `Injector`, `inject()` |
| Singleton (ambient service) | one shared, replaceable instance | the `Component` trait, see [Components](./102-getting-started.md#components) |
| Observer | something must react to what happens elsewhere | `Event`, see [Events](./109-events.md) |

## Strategy / driver

When several classes implement the same abstraction and each one handles its own case — an exporter
per file format, a payment gateway per provider — `Implementations` picks the one that supports the
case at hand. Here is an example

```php
interface InvoiceExporter
{
    public static function supports(string $format): bool;
}

class CsvExporter implements InvoiceExporter
{
    public static function supports(string $format): bool
    {
        return 'csv' === $format;
    }
}

// Asks every discovered implementation, then builds the one that answered true
$exporter = Implementations::findOrFail(InvoiceExporter::class, fn (string $class) => $class::supports($format), for: $format);
```

Adding a format means adding a class : nothing lists the exporters. This is how Cube picks its own
query builders, migration plans and model generation adapters for a database driver.

- The callback receives each **class name**, not an instance : declare the check as a `static`
  method, so that only the chosen class gets built.
- The base can be an interface or a class, abstract classes are never candidates.
- The chosen class is built through the [`Injector`](#dependency-injection) : pass constructor
  arguments as the third parameter (`[$database]`), the other dependencies are resolved for you.
- Candidates are the classes Cube discovers, see [Class discovery](./103-applications.md#class-discovery).

| Method | Returns | When no class supports the case |
|---|---|---|
| `findOrFail($base, $supports, $args, for:)` | the first supporting implementation | throws an `ImplementationNotFoundException` naming the base, the `for:` subject and every candidate tried |
| `find($base, $supports, $args)` | the first supporting implementation | returns `null` |
| `findAll($base, $supports, $args)` | a `Bunch` of every supporting implementation | returns an empty `Bunch` |

Prefer `findOrFail()` when the case must be handled, `find()` when having no implementation is a normal
outcome.

## Registration by discovery

To collect every class of a kind, let Cube find them instead of keeping a list. Here is an example

```php
interface DashboardWidget
{
    public function render(): string;
}

// Every class implementing DashboardWidget in your applications is built and rendered
$widgets = Bunch::fromImplements(DashboardWidget::class)
    ->map(fn (DashboardWidget $widget) => $widget->render());
```

A new widget is one new file. Unlike `Implementations`, these methods build **every** class they
find, through the `Injector`, with the constructor arguments given as second parameter.

| Method | Collects |
|---|---|
| `Bunch::fromImplements($interface, $args)` | the classes implementing an interface |
| `Bunch::fromExtends($class, $args)` | the classes extending a class |
| `Bunch::fromUses($trait, $args)` | the classes using a trait |

Abstract classes are left out. To get the class names without building anything, call the
`Autoloader` directly : `Autoloader::classesThatImplements()`, `classesThatExtends()`,
`classesThatUses()`.

## Dependency injection

The `Injector` builds a class and its constructor dependencies from their types. It is what resolves
your route callbacks, and you can call it yourself

```php
class InvoiceMailer
{
    public function __construct(
        protected Database $database,  // a Component : its current instance
        protected PdfRenderer $renderer // a plain class : built the same way, recursively
    ) {}
}

$mailer = Injector::getInstance()->instanciate(InvoiceMailer::class);
$mailer = inject(InvoiceMailer::class); // the same, as a global function
```

Each typed parameter is resolved in this order

| Parameter type | Resolved as |
|---|---|
| a type given to `provide()` | the provided value (see below) |
| a class using `Component` | its `getInstance()` |
| a `ConfigurationElement` | its `resolve()` |
| any other class | built by the `Injector`, recursively |
| anything else | the parameter's default value, or an `InvalidArgumentException` |

Values passed explicitly (`instanciate(InvoiceMailer::class, [$database])`) fill the first
parameters in order and are used as they are : only the remaining parameters are resolved.

### Binding an interface

An interface cannot be built, so tell the `Injector` what to give for it, from a `Requires/` file

```php
// App/Requires/bindings.php
Injector::getInstance()->provide(PaymentGateway::class, StripeGateway::class);
```

A class name is resolved like a parameter typed with it. Give a closure instead to decide at
runtime : it receives the class asking for the dependency, and can pick the implementation with
`Implementations`

```php
Injector::getInstance()->provide(
    InvoiceExporter::class,
    fn (?string $requester) => Implementations::findOrFail(InvoiceExporter::class, fn (string $class) => $class::supports('csv'), for: 'csv')
);
```

The provided object must extend or implement the bound type, otherwise the `Injector` throws a
`RuntimeException`.

## Singleton and observer

Both are covered on their own pages :

- [Components](./102-getting-started.md#components) — the `Component` trait gives a class one shared
  instance, replaceable with `setInstance()` or for a callback with `asGlobalInstance()`.
- [Events](./109-events.md) — dispatch an `Event` and listen to it from anywhere, or let an object
  [dispatch on itself](./109-events.md#objects-that-dispatch-on-themselves) so that only its own
  listeners hear it.
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./111-models.md">Previous : Models</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./201-static-server.md">Next : Static server</a></div></td></tr></table>
