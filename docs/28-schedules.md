<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./27-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./29-queues.md">Next : Queues</a></div></td></tr></table>

# Schedules

A schedule runs a task at given moments, described by a cron expression. The `Scheduler`
component holds every schedule, and the `routine:launch` command, called every minute, runs the ones
that match, each in its own process.

Declare a schedule from any file loaded at boot — a `Schedules/`, `Cron/` or `Requires/` file of your
application, as described in [Applications and discovery](./04-applications-and-discovery.md#directory-structure)

```php
// App/Schedules/reports.php

Cube\schedule('0 6 * * 1', [ReportMailer::class, 'sendWeekly']);   // mondays at 06:00
```

Then let the host's cron call Cube every minute

```cron
* * * * * cd /var/www/shop && php do routine:launch
```

With the Docker stack of the server files, `make launch-scheduler` runs it inside the php-fpm container

```cron
* * * * * cd /srv/shop && make launch-scheduler
```

## Writing a task

A task is a public method, given as `[Class::class, 'method']`, or an invokable class, given by its
name. Cube runs it in a separate process, so it must be something that process can find again from
a name : a closure is refused, and so are arguments

```php
Cube\daily([Invoice::class, 'archiveOld']);         // a static method, or an instance method
Cube\everyHour(DeleteStaleUploads::class);          // an invokable class
```

The class is built by the `Injector`, and the method's parameters are injected the way a controller's
are

```php
class DeleteStaleUploads
{
    public function __invoke(LargeUploadManager $uploads): void
    {
        $uploads->deleteOlderThan(Cache::DAY);
    }
}
```

A task needing a value is a method of its own, or a [command](./10-commands.md) if the value comes
from whoever runs it. To run several things one after the other, write a class calling them in
order and schedule that class.

A class that does not exist, or that has no such public method, throws an
`InvalidArgumentException` as soon as the schedule is declared.

## Helpers for common rhythms

Three helpers cover the usual cases, so you only write a cron expression when you need a precise
moment

| Helper | Runs |
|---|---|
| `Cube\everyMinute(array\|string $task, int $step = 1, bool $lock = true)` | every minute, or every `$step` minutes |
| `Cube\everyHour(array\|string $task, int $step = 1, bool $lock = true)` | at minute 0, every hour or every `$step` hours |
| `Cube\daily(array\|string $task, int $step = 1, bool $lock = true)` | at midnight, every day or every `$step` days of the month |
| `Cube\schedule(string $cronExpression, array\|string $task, bool $lock = true)` | whenever the cron expression matches |

Here is an example

```php
Cube\everyMinute([Heartbeat::class, 'ping']);
Cube\everyMinute([Product::class, 'refreshStockLevels'], 5);   // every 5 minutes
Cube\everyHour(ClearExchangeRates::class, 6);                    // every 6 hours
Cube\daily([Invoice::class, 'archiveOld']);
```

## Cron expressions

The expression is the standard five-field cron syntax — minute, hour, day of the month, month, day of
the week — separated by single spaces

```php
Cube\schedule('*/15 * * * *', $task);    // every quarter of an hour
Cube\schedule('30 2 1 * *', $task);      // 02:30, first day of the month
Cube\schedule('0 8 * * 1-5', $task);     // 08:00 on weekdays
Cube\schedule('0 9,14 * * *', $task);    // 09:00 and 14:00
Cube\schedule('0 0 */2 * *', $task);     // midnight on the 1st, 3rd, 5th... of the month
```

Each field accepts one of these forms

| Form | Example | Matches |
|---|---|---|
| `*` | `*` | any value |
| a value | `30` | that value |
| a list | `1,15` | any of the values |
| a range | `1-5` | from the first to the last value, both included |
| a step | `*/10` | every tenth value, counted from the first value of the field |

| Field | Bounds |
|---|---|
| minute | `0`-`59` |
| hour | `0`-`23` |
| day of the month | `1`-`31` |
| month | `1`-`12` |
| day of the week | `0`-`7`, both `0` and `7` meaning sunday |

A step counts from the first value of its field, as in standard cron : `*/2` on the day of the month
fires on odd days, starting with the 1st. A step only follows `*` : `0-30/10` or `5/10` are refused,
and so are combinations such as `1-5,7`. Names (`mon`, `jan`) and shortcuts (`@daily`) are not
supported.

When both the day of the month and the day of the week are restricted, **either** one is enough, as in
standard cron : `0 0 1 * 1` fires on the first day of every month and on every monday. When one of them
is `*`, the other one alone decides : `0 0 * * 1` fires on mondays only. Only a bare `*` leaves a field
unrestricted — a step such as `*/2` restricts it.

An expression that is not five fields, or whose values fall outside their bounds, throws an
`InvalidArgumentException` as soon as it is declared rather than silently never matching.

## Running them

`routine:launch` runs every schedule whose expression matches the current minute. Nothing calls it
for you : call it every minute from the host's cron, or from a container's scheduler. `routine:generate`
prints the crontab lines for your project : `make launch-scheduler` for the Docker stack first, then
`php do routine:launch`. Both `cd` to the project path as Cube sees it : run from inside the container,
that is `/var/www`, so replace it with the path on the host

```sh
php do routine:generate
```

Since the expressions are matched against *now*, running the command more or less often than once a
minute makes some schedules fire twice or not at all.

`routine:launch` starts one `routine:run` process per task due, all at once, then waits for every
one of them before it exits

```
cron, every minute
  └─ php do routine:launch
       ├─ php do routine:run "App\Reports\ReportMailer" sendWeekly
       ├─ php do routine:run "App\Uploads\DeleteStaleUploads"
       └─ waits for both, logs any that exits with an error
```

A slow task does not delay the others, and a task that throws, or dies on a fatal error, leaves them
untouched : the exception is logged by its own process, and `routine:launch` logs its exit code.
Each task starts from a fresh process, so nothing it leaves in a component reaches the next one.

`routine:launch` does **not** run your queues : schedules and queue workers are launched separately.

## Overlapping runs

A task never runs alongside itself : `routine:run` holds a lock on the task while it runs. When a
task takes longer than the time between two of its runs — 7 minutes for a task due every 5 — the
next `routine:launch` skips it, logs a warning, and runs the other tasks due. The lock is a
`flock()` on a file of `Storage/Scheduler`, which the system releases even when the process dies,
so a crashed run never blocks the next one.

A task with nothing to protect can run alongside itself : give `lock: false` when you schedule it

```php
Cube\everyMinute([Heartbeat::class, 'ping'], lock: false);
```

`routine:launch` then calls `routine:run --without-lock` for it. The lock only works between
processes of one machine : running `routine:launch` from several servers runs each task once per
server.

## Running one task by hand

`routine:run` runs a single task, whether it is due or not, with the same lock as a scheduled run.
It takes the class, then the method — the class alone for an invokable class

```sh
php do routine:run "App\Reports\ReportMailer" sendWeekly
php do routine:run "App\Uploads\DeleteStaleUploads"               # an invokable class
php do routine:run "App\Reports\ReportMailer" sendWeekly --without-lock
```

It answers `1` when the task throws, and `0` when it ran or was skipped by the lock.

## Where the logs go

While a task runs, `routine:run` installs the logger of `Storage/Logs/launch.csv` as the global
`Logger` : what your tasks log through `Logger::getInstance()`, or through an injected `Logger`, lands
there along with the exceptions they throw, the skipped runs and the failed exit codes.

## Testing a schedule

Check an expression against any date, and run a task in the test's own process with
`Scheduler::run()`, which takes the same lock as `routine:run`

```php
$this->assertTrue((new CronExpression('0 8 * * 1-5'))->matches('2026-10-12 08:00'));

Scheduler::getInstance()->run([Invoice::class, 'archiveOld']);
```

`Scheduler::launch()` starts real `php do routine:run` processes from your project root, so call it
from a test only when that is what you mean to check.

## Reference

| Command | Does |
|---|---|
| `php do routine:launch` | runs the schedules matching the current minute, one process each |
| `php do routine:run <class> [method] [--without-lock]` | runs one task now, the class alone for an invokable class |
| `php do routine:generate` | prints the crontab lines calling `routine:launch` for this project, with and without Docker |

| Method | Does |
|---|---|
| `Scheduler::add(CronExpression $expression, array\|string $task, bool $lock = true)` | what the helpers call |
| `Scheduler::launch(DateTime\|string $datetime = 'now')` | starts a `routine:run` process per schedule matching that minute, and waits for them |
| `Scheduler::run(array\|string $task, bool $lock = true)` | runs one task in the current process, `false` when the lock was taken |
| `CronExpression::matches(DateTime\|string $datetime = 'now')` | whether the expression matches that minute |
| `CronExpression::everyMinute()`, `everyHour()`, `everyDayOfTheMonth()`, `everyDayOfTheWeek()` | named constructors, each taking an optional `$step` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./27-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./29-queues.md">Next : Queues</a></div></td></tr></table>
