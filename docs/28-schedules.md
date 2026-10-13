<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./27-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./29-queues.md">Next : Queues</a></div></td></tr></table>

# Schedules

A schedule runs a callback at given moments, described by a cron expression. The `Scheduler`
component holds every schedule, and the `routine:launch` command, called every minute, runs the ones
that match.

Declare a schedule from any file loaded at boot — a `Schedules/`, `Cron/` or `Requires/` file of your
application, as described in [Applications and discovery](./04-applications-and-discovery.md#directory-structure)

```php
// App/Schedules/reports.php

Cube\schedule('0 6 * * 1', fn () => ReportMailer::sendWeekly());   // mondays at 06:00
```

Then let the host's cron call Cube every minute

```cron
* * * * * cd /var/www/shop && php do routine:launch
```

## Helpers for common rhythms

Three helpers cover the usual cases, so you only write a cron expression when you need a precise
moment

| Helper | Runs |
|---|---|
| `Cube\everyMinute(callable $callback, int $step = 1)` | every minute, or every `$step` minutes |
| `Cube\everyHour(callable $callback, int $step = 1)` | at minute 0, every hour or every `$step` hours |
| `Cube\daily(callable $callback, int $step = 1)` | at midnight, every day or every `$step` days of the month |
| `Cube\schedule(string $cronExpression, callable $callback)` | whenever the cron expression matches |

Here is an example

```php
Cube\everyMinute(fn () => Heartbeat::ping());
Cube\everyMinute(fn () => Product::refreshStockLevels(), 5);   // every 5 minutes
Cube\everyHour(fn () => Cache::getInstance()->delete('rates'), 6);   // every 6 hours
Cube\daily(fn () => Invoice::archiveOld());
```

Prefer a static method or an invokable class over a long closure : the schedule file stays a list
you can read at a glance.

## Cron expressions

The expression is the standard five-field cron syntax — minute, hour, day of the month, month, day of
the week — separated by single spaces

```php
Cube\schedule('*/15 * * * *', ...);    // every quarter of an hour
Cube\schedule('30 2 1 * *', ...);      // 02:30, first day of the month
Cube\schedule('0 8 * * 1-5', ...);     // 08:00 on weekdays
Cube\schedule('0 9,14 * * *', ...);    // 09:00 and 14:00
Cube\schedule('0 0 */2 * *', ...);     // midnight on the 1st, 3rd, 5th... of the month
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

When both the day of the month and the day of the week are restricted, **both** must match — unlike
standard cron, where either one is enough. `0 0 1 * 1` fires only on a month's first day that is
also a monday.

An expression that is not five fields, or whose values fall outside their bounds, throws an
`InvalidArgumentException` as soon as it is declared rather than silently never matching.

## Running them

`routine:launch` runs every schedule whose expression matches the current minute. Nothing calls it
for you : call it every minute from the host's cron, or from a container's scheduler. `routine:generate`
prints the crontab line for your project

```sh
php do routine:generate
```

Since the expressions are matched against *now*, running the command more or less often than once a
minute makes some schedules fire twice or not at all.

Schedules run one after the other, in the order they were declared, inside the `routine:launch`
process. A callback that throws does not stop the others : the exception is logged and the next
schedule runs. For work that takes long, push it to a [queue](./29-queues.md) from the schedule
rather than doing it inline

```php
Cube\daily(fn () => ExportQueue::queue('daily-sales'));
```

`routine:launch` does **not** run your queues, despite what its help text says — schedules and queue
workers are launched separately.

## Where the logs go

While the schedules run, `routine:launch` installs its own logger as the global `Logger` : what your
callbacks log through `Logger::getInstance()` lands in `Storage/Logs/launch.csv`, along with the
exceptions they throw.

## Testing a schedule

The `Scheduler` component can be driven by hand with any date, which is how you check an expression
does what you meant

```php
$this->assertTrue((new CronExpression('0 8 * * 1-5'))->matches('2026-10-12 08:00'));

Scheduler::getInstance()->launch('2026-10-12 08:00');   // runs what matches that minute
```

## Reference

| Command | Does |
|---|---|
| `php do routine:launch` | runs the schedules matching the current minute |
| `php do routine:generate` | prints the crontab line calling `routine:launch` for this project |

| Method | Does |
|---|---|
| `Scheduler::add(CronExpression $expression, callable $callback)` | what the helpers call |
| `Scheduler::launch(DateTime\|string $datetime = 'now')` | runs the schedules matching that minute |
| `CronExpression::matches(DateTime\|string $datetime = 'now')` | whether the expression matches that minute |
| `CronExpression::everyMinute()`, `everyHour()`, `everyDayOfTheMonth()`, `everyDayOfTheWeek()` | named constructors, each taking an optional `$step` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./27-http-client.md">Previous : Http client</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./29-queues.md">Next : Queues</a></div></td></tr></table>
