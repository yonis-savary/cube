# Cube

Cube is a back-end-only web framework for PHP 8 (`Cube\` → `src/`), distributed as a composer
library (`yonis-savary/cube`). An application installs it, copies `server/*` at its root and
declares its configuration in `cube.php`.

## AI usage in this repository

`README.md` states it explicitly : **Cube's source code is written without AI, and stays that
way.** Documentation is the exception — `docs/` may be generated or updated by an agent.

So, by default : read `src/` as much as needed, document it, review it, report what you find — but
do not author framework source. If a task seems to require touching code, propose the change and
let the maintainer write it, unless he explicitly asks you to.

## Philosophy

These four rules decide every design argument in this repository:

1. **Back-end focus.** Routing, validation, models, migrations, CLI, queues, websockets. No
   front-end framework, no template engine beyond plain-PHP views.
2. **Clean architecture, no useless abstraction layer.** An interface exists when a second
   implementation exists. A class exists when it carries behaviour or data, not to satisfy a
   pattern.
3. **Complete PHPDoc / IDE support, no magic.** Everything is discoverable by an IDE:
   `@template`, `class-string<T>`, `@return static`. No `__call`, no facades, no string-keyed
   service container.
4. **Shortest readable code.** Guard returns, no `else` after `return`, `??=` defaults, `match` over branch ladders.

## Where things are

@.claude/architecture.md

## How to write code here

@.claude/guidelines.md

## How to write documentation here

@.claude/documentation.md

## Commands

```sh
make test            # composer install + docker compose up + full suite + cleanup
make test-dirty      # same, without the cleanup step
make workflow-test   # tests only (what CI runs, services already up)
make test filter=Foo # restrict --filter
make test jobs=4     # cap the number of ParaTest processes
make test-serial     # everything in one phpunit process, when a parallel run confuses a failure
make cleanup         # remove test databases/caches left behind
php do cube:help     # list every command (framework + application)
```

Tests run on **ParaTest**, one process per test class across the three suites — every ambient
service is a per-process singleton and the database providers name what they create randomly,
so workers never share state.

`RouterTest::testPerformances` asserts on wall-clock time and routes once before measuring :
the first `Router::route()` of a process spends ~15 ms resolving a callback through the
`Injector`, one-off work that would otherwise land in the measurement.

Integration and APCu suites need the docker services (`compose.yml`: MySQL, Postgres, Redis,
nginx+php-apcu). Unit suite alone runs without them.

The APCu image **bakes the framework source in at build time** (`COPY . /vendor/cube`), so a
source change only reaches it through `docker compose up --build`. That container also answers
`loaded_with_apcu: false` on its first request only, so running the APCu suite twice without
`docker compose restart php-apcu-test` fails the second time.
