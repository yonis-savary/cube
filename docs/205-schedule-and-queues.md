<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./204-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./206-websockets.md">Next : Websockets</a></div></td></tr></table>

# Schedule and Queues

Two ways to do work outside a request : **schedules** run something at a given time, **queues** run
something as soon as a worker picks it up. Both are driven from the command line, so a container or a
system cron is all the infrastructure you need.

## Schedules

Declare a schedule from any file loaded at boot — a `Schedules/`, `Cron/` or `Requires/` file of your
application, as described in [Applications](./103-applications.md).

```php
// App/Schedules/reports.php

Cube\schedule('0 6 * * 1', fn () => ReportMailer::sendWeekly());
```

Four helpers cover the common rhythms, so you only write a cron expression when you need a precise
moment

| Helper | Runs |
|---|---|
| `Cube\everyMinute(callable $callback, int $step = 1)` | every minute, or every `$step` minutes |
| `Cube\everyHour(callable $callback, int $step = 1)` | at minute 0, every hour or every `$step` hours |
| `Cube\daily(callable $callback, int $step = 1)` | at midnight, every day or every `$step` days |
| `Cube\schedule(string $expression, callable $callback)` | whenever the cron expression matches |

```php
Cube\everyMinute(fn () => Heartbeat::ping());
Cube\everyHour(fn () => Cache::getInstance()->delete('rates'), 6);   // every 6 hours
Cube\daily(fn () => Invoice::archiveOld());
```

The expression is the standard five-field cron syntax — minute, hour, day of the month, month, day of
the week — and supports `*`, a value, a list, a range and a step

```php
Cube\schedule('*/15 * * * *', ...);    // every quarter of an hour
Cube\schedule('30 2 1 * *', ...);      // 02:30, first day of the month
Cube\schedule('0 8 * * 1-5', ...);     // 08:00 on weekdays
```

An expression that is not five fields, or whose values fall outside their bounds, throws as soon as
it is declared rather than silently never matching.

### Running them

One command runs every schedule whose expression matches the current minute

```sh
php do routine:launch
```

Nothing runs it for you : call it every minute from the host's cron, or from a container's scheduler

```cron
* * * * * cd /var/www && php do routine:launch
```

Since the expressions are matched against *now*, running the command more or less often than once a
minute will make some schedules fire twice or not at all.

## Queues

A queue processes items asynchronously. Extend `Queue` and put the work in `__invoke()` — its
parameters are what you push.

```php
class CalculatorQueue extends Queue
{
    public function __invoke(int $a, int $b)
    {
        $this->logger->info($a + $b);
    }
}
```

Push items from anywhere, statically or through an instance

```php
CalculatorQueue::queue(2, 3);

// or
$queue = new CalculatorQueue();
$queue->push(2, 3);
```

And run the worker

```sh
php do cube:queue --queue=CalculatorQueue

# -l attaches the global logger to stdout, which is what you want as a docker service
php do cube:queue --queue=CalculatorQueue -l
```

While a queue runs, it writes to its own log file in `Storage/Logs` — `calculatorqueue.csv` here.

| Command | Does |
|---|---|
| `php do cube:queue --queue=<class>` | runs the worker until it is stopped |
| `php do cube:queue --queue=<class> -l` | same, echoing the log to stdout |
| `php do cube:queue --queue=<class> -f` | flushes the queue and exits |

From code, `$queue->flush()` empties it, and `$queue->processNext()` handles a single item — handy
in a test.

### Customizing a queue

Two methods are meant to be overridden

```php
class CalculatorQueue extends Queue
{
    /**
     * Where jobs are stored. The local disk is used by default ; Redis is
     * advisable as soon as several workers run at once.
     */
    protected function getDriver(): QueueDriver
    {
        return new RedisQueue();
    }

    /**
     * Called when processing an item throws.
     *
     * @return bool `true` re-pushes the job, `false` drops it
     */
    protected function onError(Throwable $thrown, array $args): bool
    {
        $this->logger->logThrowable($thrown);

        return false;
    }

    public function __invoke(int $a, int $b)
    {
        $this->logger->info($a + $b);
    }
}
```

Returning `true` from `onError()` puts the job back in the queue — make sure the failure is one that
can succeed later, or the job cycles forever.

### Drivers

| Driver | Stores jobs in |
|---|---|
| `LocalDiskQueueDriver` | a directory of your `Storage`, the default |
| `RedisQueue` | a Redis server |

`RedisQueue` reads its host from the `QUEUE_REDIS_HOST` environment variable, defaulting to `redis` —
the constructor argument is currently ignored, so set the variable rather than passing a host.

```env
QUEUE_REDIS_HOST=127.0.0.1
```

A Redis service to develop against

```yaml
services:
  redis:
    image: redis:7
    ports:
      - "6379:6379"
    command: ["redis-server", "--save", "20", "1", "--loglevel", "warning"]
```

Each queue gets its own key namespace, derived from the class name, so several queues can share one
Redis server without colliding.

### Running workers in production

A worker is a long-running process : give it a supervisor that restarts it, one service per queue.

```yaml
services:
  calculator-worker:
    image: my-app
    command: ["php", "do", "cube:queue", "--queue=App\\Queue\\CalculatorQueue", "-l"]
    restart: always
```

`routine:launch` does **not** run your queues, despite what its help text suggests — schedules and
workers are launched separately.
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./204-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./206-websockets.md">Next : Websockets</a></div></td></tr></table>
