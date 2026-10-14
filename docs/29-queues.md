<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./28-schedules.md">Previous : Schedules</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./30-websockets.md">Next : Websockets</a></div></td></tr></table>

# Queues

A queue runs work outside the request, as soon as a worker picks it up. You extend `Queue`, push
items from anywhere, and run the worker with `php do cube:queue`.

Put the work in `__invoke()` — its parameters are what you push

```php
class InvoiceMailerQueue extends Queue
{
    public function __invoke(int $invoiceId, string $recipient)
    {
        $invoice = Invoice::find($invoiceId);
        // ... send it

        $this->logger->info('Sent invoice {id} to {recipient}', ['id' => $invoiceId, 'recipient' => $recipient]);
    }
}
```

Push items from a controller, a command, a schedule…

```php
InvoiceMailerQueue::queue($invoice->id, 'billing@agency.example.org');
```

And run the worker

```sh
php do cube:queue --queue=InvoiceMailerQueue
```

## Pushing items

Push statically, or through an instance

```php
InvoiceMailerQueue::queue($invoice->id, $recipient);

// or
$queue = new InvoiceMailerQueue();
$queue->push($invoice->id, $recipient);
```

The arguments are serialized when pushed and unserialized by the worker, so push scalars, arrays or
small value objects — an id rather than a model, which may have changed by the time the worker runs.

Items are processed in the order they were pushed, one at a time per worker.

## Dependencies of a queue

`queue()` and the `cube:queue` command build the queue through the
[injector](./05-dependency-injection.md), so a queue declares its collaborators in its constructor

```php
class InvoiceMailerQueue extends Queue
{
    public function __construct(
        protected Mailer $mailer,
    ) {}

    public function __invoke(int $invoiceId, string $recipient)
    {
        $this->mailer->send($recipient, Invoice::find($invoiceId));
    }
}
```

The worker builds the queue once and reuses it for every item, so a constructor dependency lives as
long as the worker does.

## Running the worker

`cube:queue` finds the class by its full name, or by its short name

```sh
php do cube:queue --queue='App\Queue\InvoiceMailerQueue'
php do cube:queue --queue=InvoiceMailerQueue

# -l echoes the queue's log to stdout, which is what you want as a docker service
php do cube:queue --queue=InvoiceMailerQueue -l
```

A short name matches whole segments of a queue class name : `InvoiceMailerQueue` or
`Queue\InvoiceMailerQueue`, never `MailerQueue`. When it matches several queues, the command refuses to
pick one and lists them : add namespace segments, or give the full name.

The worker loops until it is stopped, waiting 50 ms whenever the queue is empty. With the `pcntl`
extension, `SIGTERM` and `SIGINT` (`Ctrl-C`) let it finish the current item before leaving; from code,
`$queue->stop()` does the same.

## Logs

While a queue runs, it writes to its own log file in `Storage/Logs`, named after the class in
lowercase — `invoicemailerqueue.csv` here. `$this->logger` (or the `info()`, `error()`… shortcuts of
the `HasLogger` trait) writes there. The worker also installs that logger as the global `Logger`
while it runs, so whatever your code logs through `Logger::getInstance()` lands in the same file.

With `-l`, the same lines are echoed to stdout.

## When an item fails

An exception thrown by `__invoke()` does not stop the worker. The queue logs a warning and the
message, then asks `onError()` what to do with the item : `true` pushes it back at the end of the
queue, `false` (the default) drops it

```php
class InvoiceMailerQueue extends Queue
{
    protected function onError(Throwable $thrown, array $args): bool
    {
        $this->logThrowable($thrown);

        return $thrown instanceof MailerUnavailableException;
    }

    // ...
}
```

Returning `true` for a failure that cannot succeed later makes the item cycle forever : keep it for
transient errors.

## Choosing where items are stored

Override `getDriver()` to change the storage. The local disk is the default; Redis is advisable as
soon as several workers, or several machines, share a queue

```php
class InvoiceMailerQueue extends Queue
{
    protected function getDriver(): QueueDriver
    {
        return new RedisQueue();
    }

    // ...
}
```

| Driver | Stores items in |
|---|---|
| `LocalDiskQueueDriver` | one file per item, in `Storage/Queues/<identifier>` — the default |
| `RedisQueue` | a Redis list |

Each queue gets its own namespace, `Queue::getIdentifier()` (an md5 of the class name), so several
queues share one directory or one Redis server without colliding. Several workers can process the
same local queue : each item is claimed by renaming its file, so only one worker gets it.

### Redis

`RedisQueue` takes a host, a port and a password. Without them, it reads the `QUEUE_REDIS_HOST`
(defaulting to `redis`) and `QUEUE_REDIS_PASSWORD` environment variables, and connects to port
`6379`. Set a password in production

```env
QUEUE_REDIS_HOST=127.0.0.1
QUEUE_REDIS_PASSWORD=a-long-random-secret
```

Redis items are unserialized by the worker, so `RedisQueue` signs each one with your `APP_KEY` and
drops (with a warning) an item it did not sign. `APP_KEY` must be set (32 bytes at least,
`php do key:generate` makes one) and must be the same for the application pushing items and the
workers processing them. Items still waiting when you change it are dropped.

A lost connection is reopened on the next poll. While Redis stays unreachable, the worker keeps
running : it logs a warning in the queue's log and tries again a second later.

Here is a Redis service to develop against

```yaml
services:
  redis:
    image: redis:7
    ports:
      - "6379:6379"
    command: ["redis-server", "--save", "20", "1", "--loglevel", "warning"]
```

## Running workers in production

A worker is a long-running process : give it a supervisor that restarts it, one service per queue

```yaml
services:
  invoice-mailer:
    image: my-app
    command: ["php", "do", "cube:queue", "--queue=App\\Queue\\InvoiceMailerQueue", "-l"]
    restart: always
```

The worker holds your code in memory : restart it after a deployment, or it keeps running the old
version.

## Testing a queue

`processNext()` handles a single item and returns `false` when there was nothing to do, so a test can
push and process without a worker

```php
$queue = new InvoiceMailerQueue();
$queue->flush();

$queue->push($invoice->id, 'billing@agency.example.org');

$this->assertTrue($queue->processNext());
$this->assertFalse($queue->processNext());
```

## Reference

| Command | Does |
|---|---|
| `php do cube:queue --queue=<class>` (or `-q`) | runs the worker until it is stopped |
| `php do cube:queue --queue=<class> -l` (or `--log`) | same, echoing the queue's log to stdout |
| `php do cube:queue --queue=<class> -f` (or `--flush`) | empties the queue and exits |

| Member | Does |
|---|---|
| `__invoke(...)` | processes one item — required |
| `getDriver(): QueueDriver` | where items are stored, `LocalDiskQueueDriver` by default |
| `onError(Throwable $thrown, array $args): bool` | `true` re-pushes the failed item, `false` (default) drops it |
| `stopSignals(): array` | signals that stop the worker, `[SIGTERM, SIGINT]` by default |
| `static queue(...$args)` | builds the queue through the injector and pushes an item |
| `push(...$args)` | pushes an item |
| `processNext(): bool` | processes one item, `false` when the queue was empty or the item failed |
| `flush()` | empties the queue |
| `loop(?Logger $attachedLogger = null)` | runs the worker, what `cube:queue` calls |
| `stop()` | asks a running loop to leave after the current item |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./28-schedules.md">Previous : Schedules</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./30-websockets.md">Next : Websockets</a></div></td></tr></table>
