# Changelog

## 0.2.0

The release that makes Cube production-ready : a security audit (`S01`–`S46` in `ROADMAP.md`), the bugs
found while rewriting the documentation (`R01`–`R36`), and an API settled before it is promised.
0.x versions may still break compatibility in a minor release : every break is listed below with the
change it asks from your application.

### Breaking changes

**Collections**
- `Bunch::of()` is renamed `Bunch::wrap()`, same behaviour : replace every call.
- `Bunch::range(int $length)` now gives `0` to `$length - 1`. The former `range($end, $start, $step)`
  becomes `Bunch::rangeFrom($start, $end, $step)`, bounds included.
- `partitionFilter()` always returns its `0` and `1` groups, empty when nothing fell in them.

**HTTP**
- `Response::ok()`, `created()`, `notFound()`… encode any value that is not a string as JSON
  (`Response::fromContent()`), where a model or an array used to throw a `TypeError`.
- `Response::found()`, `seeOther()` and `movedPermanently()` take the target URL and set `Location`,
  like `temporaryRedirect()` and `permanentRedirect()`. Their argument used to be the body.
- `ResponseAssert::assertPatientContent()` is now `assertPartialContent()`, and
  `assertVariantAlsoNegociates()` is now `assertVariantAlsoNegotiates()`.
- `Param::from()` returns a `Rule`.
- Empty file inputs (`UPLOAD_ERR_NO_FILE`) are dropped at the edge : `$request->upload('x')` answers
  `null` for a file input left empty.

**Cache and deployment**
- `CacheDriverInterface::clear()` takes a `string $keyPrefix = ''` parameter : add it to your own
  cache drivers. `Cache::clear()` on a `child()` now only empties that child's keys.
- `PostDeployment` empties the class index cache, the remembered routes and the configuration cache,
  instead of the whole `Cache` : run `php do cache:clear` to empty everything. Run
  `configuration:cache` **after** dispatching it.
- `fakerphp/faker` moved from `require` to `suggest` : add it to your development dependencies
  (`composer require --dev fakerphp/faker`) to use model factories or OpenAPI model examples.

**Configuration and injection**
- `ConfigurationElement::resolve()` throws a `RuntimeException` naming the element when it is missing
  from `cube.php` and has required options (`AuthenticationConfiguration`), instead of an
  `ArgumentCountError`.
- `RequestContext` never resets the `Injector` : bindings declared with `provide()` stay available
  inside `run()` / `oneShot()`.

**Security**
- `AuthenticationProvider` gains `getCredentialFields(): array` : implement it in your own providers
  (return `[]` when there is none). `Authentication::login()` keeps those fields out of the session.
- `HasScopedSession` scopes changed key once (`md5(static::class)`) : scoped session values, the
  authentication one included, are lost on the first request after the upgrade, so users log in again.

**Data**
- `SavedModel::$database` is never `null`.
- `make:dto` generates ISO dates as `?string`.
- `AutoDataToObject::toArray()` exports public properties only.
- The migration history is ordered by insertion id rather than `created_at`.
- A `HasOne` without a matching row is `null`, through `load()` as well as a query's `with()` (which
  used to give a model holding only `null` fields). Reading it no longer throws.
- Removed : `MigrationManager::catchUpTo()`, `Plan::allowExperimentalFeatures()`,
  `UnsupportedByDBMSException`.
- `MigrationManager` is one class for every driver : the `Migration\Adapters\*` classes are removed,
  build `new MigrationManager($database, $configuration)` instead.
- `QueryBuilder` holds the SQL rendering, and the `Postgres` / `SQLite` builders no longer extend
  `MySQL`. A custom builder declares `getIdentifierDelimiters()` and `getUnboundedLimit()`.
- The `MySQL`, `PostgreSQL` and `SQLite` plans extend `Migration\SQLPlan`, and the
  `ModelGenerator` adapters no longer extend `SQLite` : a custom adapter implements `listTables()`,
  `listForeignKeys()`, `listColumns()` and `getModelField()`.
- SQLite quotes every table and column name it renders.

**Background work and CLI**
- Schedules take `[Class::class, 'method']` or an invokable class name instead of a callable :
  `routine:launch` runs every task due in its own `routine:run` process, in parallel, and a task
  still running is skipped by the next launch instead of running twice at once (`lock: false` lets
  it overlap). Turn each closure given to `Cube\schedule()`, `everyMinute()`, `everyHour()`,
  `daily()` or `Scheduler::add()` into a method or an invokable class.
- Cron expressions combine a restricted day of the month and a restricted day of the week with OR,
  like crontab.
- `cube:queue --queue=<name>` and `cube:dispatch --event=<name>` refuse a short name matching several
  classes, and list them.
- `websocket:serve` logs nothing without `-l` / `--log` (the option used to have no effect, and the
  server logged to `cube.csv`).
- `Channel::emit()` waits for the websocket server's answer and returns whether it accepted the
  signal. `Channel::redirect()` points to `localhost` when the server binds a wildcard address.

**Utilities**
- `Text::dontStartsWith()` and `Text::dontEndsWith()` are renamed `Text::leftWordTrim()` and
  `Text::rightWordTrim()`, same behaviour : replace every call.

**Server files** — copy them again, or apply the changes to your copy :
- `Makefile` : `deploy` builds the image first, then runs `migrate:migrate`, `PostDeployment` and
  `configuration:cache` on it before starting php-fpm.
- Two Docker stacks from one multi-stage `Docker/Dockerfile.php`. `docker-compose.yml` is production :
  the project is copied into the image, OPcache never revalidates, composer installs
  `--no-dev --classmap-authoritative`. The new `docker-compose.override.yml` is development, read by a
  plain `docker compose up` (`make dev`) : the project is mounted, `vendor/` lives in a volume. The
  `Makefile` production targets pass `-f docker-compose.yml`.
- `Makefile` : `make exec` opens a bash in php-fpm and `make launch-scheduler` runs `routine:launch` in
  it, both as `www-data` ; call the latter from the host's cron.
- The Docker stack answered no request : nginx and php-fpm each declared their own `/var/www` volume,
  so nginx's stayed empty (nginx now takes php-fpm's volumes with `volumes_from`), and the mounted
  `Storage/` belonged to the host user, so php-fpm could not even write its logs. The image now
  gives `www-data` the ids of the host user, `UID` and `GID` in `.env` (`1000` by default), and every
  process runs as that user, never as root : rebuild the image, and if a previous stack left
  `Storage/` to uid 33, `sudo chown -R $(id -u):$(id -g) Storage` once. The Makefile creates
  `Storage/` before starting the stack.
- `docker-compose.yml` names the image `${COMPOSE_PROJECT_NAME}-php`, so that background services
  (queue workers, websocket server) run on it, and `Docker/Dockerfile.php` installs `pcntl`, so that
  a worker stopped by Docker finishes its current item.
- `Tests/bootstrap.php` : `TestContext::useTemporaryStorage()`, which removes `Storage/Tests-*` at the
  end of the run.
- New `AGENTS.md` and `CLAUDE.md`, pointing coding agents to `vendor/yonis-savary/cube/ai/FRAMEWORK.md`.

### Added

- `php do migrate:rollback [--steps=N]`, calling `Migration::down()`.
- `php do routine:generate` also prints the crontab line for the Docker stack, `make launch-scheduler`,
  before the plain one.
- `Bunch::fromAssoc()`, `Bunch::rangeFrom()`, and `Bunch` is iterable with `foreach`.
- `ModelResource`, `ModelFactory` / `Model::factory()`, and their OpenAPI support
  (`#[ResourceResponse]`).
- `ModelFactory::with()` makes the named relations along with the model, and `#[ResourceResponse]` /
  `#[ModelResponse]` take a `with` list of the relations an endpoint loads. In the OpenAPI document,
  a model schema now references the schema of each relation's model, optional, instead of inlining it.
- `ModelField::references()` accepts a model class as well as a table name.
- `TestContext::useTemporaryStorage()`, `Configuration::removeFromCache()`, `Router::cleanCache()`.
- `CubeTestCase` resets `MockServers` after each test, and `assertModelExists()` works.
- `php do test` forwards its arguments to PHPUnit and answers its exit code.
- `ai/FRAMEWORK.md`, guidelines for coding agents writing an application with Cube.
- The documentation is rewritten in reading order, from installation to deployment.
- Static analysis : PHPStan level 6 in the CI (`make analyse`), current errors kept in a baseline.

### Fixed

See `ROADMAP.md` for the detail of each point : `S01`–`S46` (security audit) and `R01`–`R36`.

Also fixed :
- Postgres and SQLite `UPDATE` queries with a join but no `where()` lost their `WHERE` keyword.
- `HttpMessage::getJSON()` decodes the displayed body, so it reads a `Response::file()`.
- `ResponseAssert::json()` throws on an invalid body instead of answering `null`.
- `ValidationReturn::mergeWith()` threw on keyed errors ; `addErrorKey()` appends to an existing key.
- `Plan::addUniqueIndex()` creates the index on SQLite instead of logging a warning.
- `MigrationManager` only runs the `.php` files of `Migrations/`.
- `models:generate` refuses a foreign key pointing to a table whose name is not a plain identifier.
- `TestContext::useTemporaryStorage()` no longer empties a directory a symbolic link points to.
- A `#[ResourceResponse]` example never reads the database : a relation the resource loads is `null`.
- A `Logger` destroyed at shutdown after PHP closed its stream no longer throws a `TypeError`.
