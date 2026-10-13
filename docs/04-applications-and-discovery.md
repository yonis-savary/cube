<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./03-configuration.md">Previous : Configuration</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./05-dependency-injection.md">Next : Dependency injection</a></div></td></tr></table>

# Applications and discovery

An application is a directory of your project declared in `cube.php` : Cube loads its special
directories (routes, views, assets, files to include at boot) and discovers its classes by type, so
adding a controller, a command or a migration is adding one file.

Here is how you declare one

```php
// cube.php
return [
    new Applications('App'),
];
```

You can declare several, which can be considered as modules : `new Applications('App', 'Billing')`.
The paths are relative to your project, or absolute. `new Applications()` alone loads `App`, and a
path that is not a directory is skipped with a warning in your logs.

Each application directory must be autoloaded by composer (see
[Installation](./02-installation.md)), otherwise its classes are not discovered.

## Directory structure

Inside an application, a few directory names carry meaning. They must sit directly in the
application directory. The sub-directories of every one of them are explored too, except for
`Migrations/`.

| Directory | Content | Used by |
|---|---|---|
| `Routes/`, `Router/` | route files, with `$router` in scope | the `Router`, see [Routing and middlewares](./11-routing-and-middlewares.md) |
| `Requires/`, `Includes/`, `Helpers/`, `Schedules/`, `Cron/` | PHP files included at boot (global functions, bindings, `Cube\schedule()` calls) | the `Autoloader`, see [Schedules](./28-schedules.md) |
| `Views/` | plain-PHP views for `Cube\render()` | the `Renderer`, see [Views and assets](./14-views-and-assets.md) |
| `Assets/` | files served or inserted as assets | `AssetServer`, `AssetsInserter`, see [Views and assets](./14-views-and-assets.md) |
| `Migrations/` | migration files (name set by `MigrationManagerConfiguration`) | the migration manager, see [Migrations](./17-migrations.md) |

Only the `.php` files of `Routes/`, `Router/` and the included directories are loaded : a
`README.md` or an editor backup left there would otherwise be printed into every response.

A view prints nothing escaped by itself : wrap every value with `Cube\e()`, which escapes it for HTML

```php
<h1><?= Cube\e($client->name) ?></h1>
```

Everything else is yours to organize : `Models/`, `Controllers/`, `Commands/`, `Queue/`,
`Channels/`... are found by their type, not by their path. Here is a typical layout

```
App/
├── Commands/CountProducts.php
├── Controllers/ProductController.php
├── Controllers/Requests/StoreProductRequest.php
├── Migrations/2025_02_03_08_47_17_create_product_table.php
├── Models/Product.php
├── Requires/bindings.php
└── Views/product/details.php
```

You can retrieve every set of files with

```php
Autoloader::getRoutesFiles();
Autoloader::getRequireFiles();
Autoloader::getAssetsFiles();
Autoloader::getViewFiles();
```

## Class discovery

Controllers, commands, queues, channels, factories and every other class Cube finds by itself are
looked for in two places only : Cube's own sources and your applications directories. A class
elsewhere — a shared `src/` folder, your `Tests/` directory, a vendor package — is not discovered.

To explore another directory, add it from a `Requires/` file

```php
// App/Requires/discovery.php
Autoloader::addToExploreMap('vendor/acme/shop-controllers/src');
```

The path is relative to your project, or absolute. The directory must be autoloaded by composer
(PSR-4 or classmap) : Cube derives each class name from your `composer.json` mapping, and only
keeps a PSR-4 file declaring a class, interface, trait or enum named after the file.
`addToExploreMap()` throws an `InvalidArgumentException` when the path is not a directory.

Discovery results are cached per set of explored directories, so adding a directory never serves
results computed without it.

In production, the list of applications files and the class index are cached : a new controller or
route file is not noticed until the cache is emptied. See [Deployment](./34-deployment.md).

## Registration by discovery

To collect every class of a kind, let Cube find them instead of keeping a list. Here is an example

```php
interface DashboardWidget
{
    public function render(): string;
}

// Every class implementing DashboardWidget is built, then rendered
$widgets = Bunch::fromImplements(DashboardWidget::class)
    ->map(fn (DashboardWidget $widget) => $widget->render());
```

A new widget is one new file. These methods build **every** class they find through the
[`Injector`](./05-dependency-injection.md), with the constructor arguments given as second parameter.

| Method | Collects |
|---|---|
| `Bunch::fromImplements($interface, $args)` | the classes implementing an interface |
| `Bunch::fromExtends($class, $args)` | the classes extending a class |
| `Bunch::fromUses($trait, $args)` | the classes using a trait, directly, through a parent or through another trait |

Abstract classes are left out. To get the class names without building anything, call the
`Autoloader` directly

```php
Autoloader::classesThatImplements(DashboardWidget::class);
Autoloader::classesThatExtends(Controller::class);
Autoloader::classesThatUses(Component::class);

// Abstract classes included
Autoloader::classesThatExtends(Controller::class, rejectAbstracts: false);
```

This is how Cube collects your controllers, commands, websocket channels and events.

## One implementation per case

When several classes implement the same abstraction and each one handles its own case — an exporter
per file format, a payment gateway per provider — `Implementations` picks the one that supports the
case at hand. Here is an example

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

    public function export(Invoice $invoice): string
    {
        // ...
    }
}

// Asks every discovered implementation, then builds the one that answered true
$exporter = Implementations::findOrFail(
    InvoiceExporter::class,
    fn (string $class) => $class::supports($format),
    for: $format,
);
```

Adding a format means adding a class : nothing lists the exporters. Never write a `match` or an
array mapping a key to a class, that is the registry this replaces. Cube picks its own query
builders, migration plans and model generation adapters for a database driver this way.

- The callback receives each **class name**, not an instance : declare the check as a `static`
  method, so that only the chosen class gets built.
- The base can be an interface or a class. Abstract classes are never candidates.
- The chosen class is built through the [`Injector`](./05-dependency-injection.md) : pass constructor
  arguments as the third parameter (`[$database]`), the other dependencies are resolved for you.
- Candidates are the discovered classes, see [Class discovery](#class-discovery).

| Method | Returns | When no class supports the case |
|---|---|---|
| `findOrFail($base, $supports, $args, for:)` | the first supporting implementation | throws an `ImplementationNotFoundException` naming the base, the `for:` subject and every candidate tried |
| `find($base, $supports, $args)` | the first supporting implementation | returns `null` |
| `findAll($base, $supports, $args)` | a `Bunch` of every supporting implementation | returns an empty `Bunch` |

Prefer `findOrFail()` when the case must be handled, `find()` when having no implementation is a
normal outcome.

To let the `Injector` give the right implementation to a constructor typed with the interface, bind
it with `provide()` : see [Dependency injection](./05-dependency-injection.md).

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./03-configuration.md">Previous : Configuration</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./05-dependency-injection.md">Next : Dependency injection</a></div></td></tr></table>
