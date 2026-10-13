<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./08-events.md">Previous : Events</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./10-commands.md">Next : Commands</a></div></td></tr></table>

# Logging

Logging goes through the `Logger` component, which implements the
[PSR-3](https://www.php-fig.org/psr/psr-3/) `LoggerInterface` and writes to a CSV file in
`Storage/Logs`.

```php
Logger::getInstance()->info('Order {order} paid by {customer}', [
    'order' => $order->id,
    'customer' => $customer->email,
]);
```

Write the message as a template and put the values in the context : Cube replaces each `{key}` with
its value, and your messages stay identical from one call to the next, which is what makes logs
searchable.

## Levels

Every PSR-3 level has its method, and `log()` takes any level, your own included

```php
$logger = Logger::getInstance();

$logger->debug('Cart {cart} recalculated', ['cart' => $cart->id]);
$logger->info('Some message');
$logger->notice('Some message');
$logger->warning('Some message');
$logger->error('Some message');
$logger->critical('Some message');
$logger->alert('Some message');
$logger->emergency('Some message');

$logger->log('audit', 'Price of {product} changed', ['product' => $product->id]);
```

Context values that are not strings are converted : numbers as written, `true`/`false`/`null` as
words, arrays and objects through `print_r()` on one line. A value holding a `{placeholder}` is never
expanded itself.

## Logging an exception

`logThrowable()` writes the exception class, its message, the file and line, then the stack trace,
one line each, at the `error` level

```php
try {
    $gateway->charge($order);
} catch (PaymentException $exception) {
    Logger::getInstance()->logThrowable($exception);
}
```

You rarely need it for uncaught exceptions : Cube logs them for you (see
[Files Cube writes](#files-cube-writes)).

## Quick debugging helpers

`Cube\debug()`, `Cube\info()`, `Cube\notice()`, `Cube\warning()`, `Cube\error()`, `Cube\critical()`,
`Cube\alert()` and `Cube\emergency()` log **values** to the current `Logger`, one line per value

```php
debug($order, $cart);   // two lines : the order, then the cart
```

They take no template and no context : `info('{user} signed in', [...])` writes the text as is,
then the array on a second line. Use the `Logger` methods for templated messages.

## Logging to another file

`Logger::forFile()` gives the logger of a file in `Storage/Logs`, creating it on first use and
handing the same instance back afterwards

```php
$paymentLogger = Logger::forFile('payments.csv');
$paymentLogger->info('Charged {amount}', ['amount' => $order->total]);
```

Prefer `forFile()` to `new Logger('payments.csv')` : only one logger may write to a file, and a second
`new Logger()` on the same file throws a `RuntimeException`. Give a `Storage` as second parameter to
write somewhere else than `Storage/Logs`.

To send what some code logs to a file of its own, make that logger the current one while it runs
(see [Components](./06-components.md#replacing-the-instance))

```php
Logger::forFile('import.csv')->asGlobalInstance(fn () => $importer->run());
```

### A logger per class

The `HasLogger` trait gives a class its own log file, named after the class in lower case
(`InvoiceExporter` writes to `invoiceexporter.csv`), and the PSR-3 methods on itself

```php
class InvoiceExporter
{
    use HasLogger;

    public function export(Invoice $invoice): void
    {
        $this->info('Exporting invoice {id}', ['id' => $invoice->id]);
    }
}
```

`$this->getLogger()` gives the underlying `Logger`. Assign `$this->logger` to write somewhere else.

## Forwarding to another logger

`attach()` forwards every line a logger writes to another `LoggerInterface`, optionally for some
levels only

```php
$errorsLogger = Logger::forFile('errors.csv');

// Every warning or worse written by the main logger is also written to errors.csv
Logger::getInstance()->attach($errorsLogger, ['warning', 'error', 'critical', 'alert', 'emergency']);
```

The logger you call `attach()` on is the source, the one you pass is the destination.

A logger is an [`EventDispatcher`](./08-events.md#objects-that-dispatch-on-themselves) : it dispatches
a `LoggedMessage` (`level`, `message`, `context`) on itself for every line, which is what `attach()`
listens to, and what you can listen to as well.

## Other loggers

| Class | Writes |
|---|---|
| `Logger` | to a CSV file |
| `StdOutLogger` | to the standard output, as `[date] LEVEL message` ; `php do cube:queue --log` attaches one to the queue's logger |
| `NullLogger` | nowhere |

They are `Logger` subclasses, so they can replace the current logger. Replace it through `Logger`
itself : `asGlobalInstance()` called on a `NullLogger` would only set `NullLogger::getInstance()`

```php
Logger::withInstance(new NullLogger(), fn () => $importer->run()); // silence the import
```

Anything typed `Psr\Log\LoggerInterface` and built by the [`Injector`](./05-dependency-injection.md)
receives the current `Logger`, so PSR-3 libraries log through Cube.

## File format

Log files are tab-separated, with a `Datetime`, `Level` and `Message` header row written when the
file is created

```
Datetime	Level	Message
'2026-10-13 09:41:07.512'	INFO	'Order 1042 paid by ada@example.org'
```

- the date holds milliseconds, the level is written in upper case ;
- a message holding several lines is written as one row per line, all with the same date.

## Files Cube writes

All of them live in `Storage/Logs`

| File | Holds |
|---|---|
| `cube.csv` | everything written to `Logger::getInstance()` |
| `fatal.csv` | uncaught exceptions, with their trace |
| `warnings.csv` | PHP deprecation notices |
| `console.csv` | what commands print with `Console::log()` |
| `socket.csv`, `socket-fatal.csv` | the [unix socket server](./31-unix-socket-server.md) |
| `broadcast-client.csv` | the [websocket](./30-websockets.md) broadcast client |
| `<class>.csv` | a class using `HasLogger`, queues included (`launch.csv` for `php do routine:launch`) |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./08-events.md">Previous : Events</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./10-commands.md">Next : Commands</a></div></td></tr></table>
