<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./09-logging.md">Previous : Logging</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./11-routing-and-middlewares.md">Next : Routing and middlewares</a></div></td></tr></table>

# Commands

The `do` script at the root of your project launches any command of the framework or of your
applications. A command is a `Command` subclass, found by class discovery.

```sh
php do web:serve
```

Here, `web` is the *scope* and `serve` the *name* of the command. `php do cube:help` lists every
command available.

## Creating a command

Create a class extending `Command` anywhere in your application (`App/Commands/` by convention) and
implement `execute()`, which returns the exit code

```php
namespace App\Commands;

class CountProducts extends Command
{
    public function getHelp(): string
    {
        return 'Print the number of products in the catalog';
    }

    public function execute(Args $args): int
    {
        Console::log(Console::withGreenColor(count(Product::select()->fetch()).' products'));

        return 0;
    }
}
```

Nothing else to declare : launch it with its full identifier, or with its name alone when no other
command shares it

```sh
php do app:count-products
php do count-products
```

When several commands answer to the same name, `do` lists them and asks for the full identifier.

## How the identifier is derived

| Part | Default | Example for `App\Commands\CountProducts` |
|---|---|---|
| scope, `getScope()` | the root namespace, in kebab case | `app` |
| name, `getName()` | the class name, in kebab case | `count-products` |
| full identifier, `getFullIdentifier()` | `scope:name` (final, cannot be overridden) | `app:count-products` |

Override `getScope()` to group several commands under one word, the way the framework does with
`models:` or `make:`

```php
class ImportCatalog extends Command
{
    public function getScope(): string
    {
        return 'catalog'; // php do catalog:import-catalog
    }

    // ...
}
```

## Reading the arguments

`execute()` receives an `Args`, built from what follows the command identifier. Every option holds
the list of the values that follow it, until the next option

```php
// php do app:import -s supplier-a -f file1.csv -f file2.csv --file file3.csv --dry-run

$args->getValue('-s', '--supplier');      // 'supplier-a'
$args->getValues('-f', '--file');         // ['file1.csv', 'file2.csv', 'file3.csv']
$args->has('--dry-run');                  // true
$args->getValue('-l', '--limit', 100);    // 100, the default, as the option is missing
$args->dump();                            // ['-s' => ['supplier-a'], '-f' => ['file1.csv', 'file2.csv'], '--file' => ['file3.csv'], '--dry-run' => []]
$args->toString();                        // back to a command line, each option followed by all its values
```

Values given before any option are positional : `getValues()` and `getValue()` without parameters
read them

```php
// php do make:migration AddProductTable
$args->getValue();   // 'AddProductTable'
$args->getValues();  // ['AddProductTable']
```

A few parsing rules

| Written | Read as |
|---|---|
| `-f a b` | `-f` holds `a` and `b` : a value after an option belongs to it |
| `--name=value` | `--name` holds `value`, and the values after it are positional again |
| `--` | everything after it is positional, even values starting with `-` |
| `-` | a value, not an option |

The leading dashes are optional when you read : `has('f', 'file')` is the same as
`has('-f', '--file')`. Give the short form first, then the long one.

## Writing to the console

`Cube\Utils\Console` holds the output helpers

| Method | Does |
|---|---|
| `Console::print(...$lines)` | prints each line (only in the CLI) |
| `Console::log(...$lines)` | prints each line and also logs it, colors removed, to `Storage/Logs/console.csv` |
| `Console::withRedColor($text, $bright = false)` | colors a text ; one method per color (`Black`, `Red`, `Green`, `Yellow`, `Blue`, `Magenta`, `Cyan`, `White`, `Default`) |
| `Console::withRedBackground($text, $bright = false)` | the same, for the background |
| `Console::table($rows, $columns = [], $logToo = true)` | prints rows as an aligned table |
| `Console::withProgressBar($elements, $callback)` | calls `$callback` on each element under a progress bar |
| `Console::promptList($prompt, $choices, $default = null)` | asks the user to pick one choice, returns `[index, choice]` |
| `Console::chooseApplication()` | the path of your application, asking when there are several |

```php
Console::table(
    Product::select()->toBunch()->map(fn (Product $product) => [$product->name, $product->price_dollar])->get(),
    ['Name', 'Price']
);
```

`abort($message, $code = 1)` prints a message and returns the exit code, to leave `execute()` early

```php
if (!$file = $args->getValue())
    return $this->abort('A file to import is needed');
```

## Help and manual

`getHelp()` gives the one-line description shown by `php do cube:help`, and `getManual()` the text
shown by `php do cube:help <command>`, as a string or a list of lines

```php
public function getManual(): array
{
    return [
        'app:import <FILE> [--dry-run]',
        '',
        'Import a supplier catalog into the products table',
    ];
}
```

## Injected dependencies

A command is built by the [`Injector`](./05-dependency-injection.md), so its constructor can ask for
services

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

To find the command to run, `do` builds **every** command of the project : keep constructors cheap,
and open connections or read files in `execute()`.

## Calling a command from code

`call()` builds the command through the `Injector` and runs it, with the arguments you give or none

```php
CountProducts::call();
ImportCatalog::call(Args::fromArgv(['-s', 'supplier-a', '--dry-run']));
```

It returns the exit code.

## Built-in commands

| Command | Purpose |
|---|---|
| `cube:help [COMMAND]` | list every command, or print the manual of one |
| `cube:hello-world` | print `Hello World !` |
| `cube:dispatch -e <EVENT>` | dispatch an event on `Events`, by full class name or an unambiguous short name (see [Events](./08-events.md#dispatching-from-the-command-line)) |
| `cube:queue -q <QUEUE> [-f] [-l]` | run a queue (full class name or an unambiguous short name), flush it with `-f`/`--flush`, log to stdout with `-l`/`--log` (see [Queues](./29-queues.md)) |
| `cube:test` | run `vendor/bin/phpunit` from your project root, with your arguments, answering its exit code |
| `web:serve [PORT]` | serve `Public/` with PHP's built-in web server, on port `8000` by default |
| `unix:serve <SOCKET_PATH>` | serve your socket routes on a unix socket (see [Unix socket server](./31-unix-socket-server.md)) |
| `websocket:serve [-l]` | start the websocket server (see [Websockets](./30-websockets.md)) |
| `configuration:cache` | cache your configuration (see [Configuration](./03-configuration.md)) |
| `cache:clear` | clear every cache entry, listing what was removed |
| `key:generate` | print a random `APP_KEY` to paste in your `.env` |
| `make:migration [NAME]` | create a migration file in your application, asking the name when not given (see [Migrations](./17-migrations.md)) |
| `make:openapi` | write the OpenAPI document of your API (see [API documentation](./15-api-documentation.md)) |
| `make:dto` | generate data transfer object classes from a sample JSON you paste (see [Data transfer objects](./20-data-transfer-objects.md)) |
| `migrate:migrate [-s]` | apply the pending migrations ; outside production it then runs `models:generate`, unless `-s`/`--skip-generation` |
| `migrate:rollback [--steps=N] [-s]` | revert the `N` last applied migrations, 1 by default, newest first ; outside production it then runs `models:generate`, unless `-s`/`--skip-generation` (see [Migrations](./17-migrations.md#rolling-back)) |
| `models:generate [-a]` | generate a model class per table, and with `-a`/`--apis` a `ModelAPI` subclass per model (see [Models](./18-models.md)) |
| `models:to-types` | write TypeScript types for your models, to `Storage/cube-types.ts` unless `ToTypesConfiguration` says otherwise |
| `routine:generate` | print the crontab line launching `routine:launch` every minute (see [Schedules](./28-schedules.md)) |
| `routine:launch` | run the scheduled tasks due now |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./09-logging.md">Previous : Logging</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./11-routing-and-middlewares.md">Next : Routing and middlewares</a></div></td></tr></table>
