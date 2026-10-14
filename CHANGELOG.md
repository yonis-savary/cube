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
- Removed : `MigrationManager::catchUpTo()`, `Plan::allowExperimentalFeatures()`,
  `UnsupportedByDBMSException`.

**Background work and CLI**
- Cron expressions combine a restricted day of the month and a restricted day of the week with OR,
  like crontab.
- `cube:queue --queue=<name>` and `cube:dispatch --event=<name>` refuse a short name matching several
  classes, and list them.
- `websocket:serve` logs nothing without `-l` / `--log` (the option used to have no effect, and the
  server logged to `cube.csv`).
- `Channel::emit()` waits for the websocket server's answer and returns whether it accepted the
  signal. `Channel::redirect()` points to `localhost` when the server binds a wildcard address.

**Server files** — copy them again, or apply the changes to your copy :
- `Makefile` : `deploy` builds the image first, then runs `migrate:migrate`, `PostDeployment` and
  `configuration:cache` on it before starting php-fpm.
- `docker-compose.yml` : the `./App` volume is gone, the stack runs the image as built.
- `Tests/bootstrap.php` : `TestContext::useTemporaryStorage()`, which removes `Storage/Tests-*` at the
  end of the run.
- New `AGENTS.md` and `CLAUDE.md`, pointing coding agents to `vendor/yonis-savary/cube/ai/FRAMEWORK.md`.

### Added

- `php do migrate:rollback [--steps=N]`, calling `Migration::down()`.
- `Bunch::fromAssoc()`, `Bunch::rangeFrom()`, and `Bunch` is iterable with `foreach`.
- `ModelResource`, `ModelFactory` / `Model::factory()`, and their OpenAPI support
  (`#[ResourceResponse]`).
- `ModelField::references()` accepts a model class as well as a table name.
- `TestContext::useTemporaryStorage()`, `Configuration::removeFromCache()`, `Router::cleanCache()`.
- `CubeTestCase` resets `MockServers` after each test, and `assertModelExists()` works.
- `php do test` forwards its arguments to PHPUnit and answers its exit code.
- `ai/FRAMEWORK.md`, guidelines for coding agents writing an application with Cube.
- The documentation is rewritten in reading order, from installation to deployment.
- Static analysis : PHPStan level 6 in the CI (`make analyse`), current errors kept in a baseline.

### Fixed

See `ROADMAP.md` for the detail of each point : `S01`–`S46` (security audit) and `R01`–`R36`.
