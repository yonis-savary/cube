# Architecture

Folder = namespace = concern. Dependencies point inward: `Core` knows nothing about the domains,
`Env` sits above it, domain packages (`Data`, `Web`, `Security`) above those, tooling
(`Console`, `Test`) outermost. `Core\Injector` is the one sanctioned exception — it knows
`Request`, `Response` and `Model` because dependency resolution has to.

## Layers

| Namespace | Role | Entry points |
|---|---|---|
| `Core` | Kernel: class discovery, DI, error handlers, singletons | `Autoloader`, `Injector`, `Component`, `Exceptions\ResponseException` |
| `Env` | Ambient resources | `Configuration`, `Environment`, `Cache`, `Storage`, `Session`, `Logger\Logger`, `UserComponent` |
| `Data` | Collections, SQL, models, migrations, OpenAPI, DTOs | `Bunch`, `Database\Database`, `Database\Query`, `Models\Model`, `Database\Migration\*`, `OpenAPI\OpenAPIGenerator`, `DataToObject` |
| `Web` | HTTP in and out | `Router\Router`, `Router\Route`, `Http\Request`, `Http\Response`, `Http\Rules\Param`, `Controller`, `Middleware`, `ModelAPI\ModelAPI`, `Websocket\*` |
| `Security` | Authentication | `Authentication`, `Authentication\PasswordAuthentication`, `RememberMe` |
| `Event` | Local observers | `Event`, `EventDispatcher`, `Events` |
| `Queue`, `Routine` | Async work and scheduling | `Queue\Queue`, `Routine\Scheduler`, `Routine\CronExpression` |
| `Console` | CLI | `Command`, `Args`, `Commands/**` |
| `Test` | Test harness | `CubeTestCase`, `ResponseAssert`, `TestContext` |
| `Utils`, `Helpers` | Leaf utilities and global functions | `Path`, `Text`, `Shell`, `Console`, `File`, `Utils` / `Helpers/*.php` |

`src/Helpers/*.php` holds the framework's global functions (`Cube\env`, `inject`, `Cube\debug`
and the other log levels, `Cube\render`, `Cube\asset`, `Cube\schedule`, `Cube\everyMinute`,
`Cube\encrypt`/`decrypt`, `Cube\measureTimeOf`). `Autoloader::initialize()` includes them all,
so a new file dropped there is available everywhere — guard it with `function_exists` when the
name is generic.

## Request lifecycle

`server/Public/index.php` is the whole web entry point:

1. `Autoloader::initialize()` — registers error/exception handlers, includes `src/Helpers`,
   restores the APCu snapshot if present, resolves the project path, loads applications, includes
   their `Requires/` files.
2. `Request::fromGlobals()` — normalizes `$_GET`/`$_POST`/`$_FILES`/headers/body once
   (`"true"`/`"off"`/`"null"` become real scalars, JSON bodies are decoded into `post`).
3. `Router::getInstance()->route($request)`:
   - `loadRoutes()` once: `Controller` classes (discovered), then `Routes/`/`Router/` files
     (`$router` is in scope), then configured `WebAPI` instances.
   - each `WebAPI::handle()` may claim the request by returning a `Response` or a `Route`.
   - otherwise `RouteGroup::findMatchingRoute()` walks the group tree (composite); a wrong method
     collects an `InvalidRequestMethodException` so the router can answer 405, and `OPTIONS`
     answers 204 with CORS headers.
   - `Injector::getDependencies()` builds the callback arguments: a `Request` subclass is rebuilt
     and validated (422 through `ResponseException` on failure), a `Model`-typed parameter turns
     the slug into `Model::find()` (404 when missing), anything else is resolved like a
     constructor dependency.
   - `RouterCallStack` runs the middlewares as a re-entrant continuation chain, then the callback.
     A returned `Model`/`Bunch`/array is recursively converted to a JSON `Response`.
4. `$response->logSelf()`, `display()`, and `Shell::logRequestAndResponseToStdOut()`.

`ResponseException` is the sanctioned way for a deep layer to abort with a finished
`Response` — the router unwraps it. Anything else that escapes hits the exception handler
installed by the autoloader: logged to `fatal.csv`, 500 to the client (message + trace only
outside `prod`), with a bare-bones fallback if logging itself fails.

## Discovery, configuration, caching

**Registration is derived, never declared.** `Autoloader` builds a class index from the composer
classmap plus every PSR-4 directory, then answers `classesThatExtends()` /
`classesThatImplements()` / `classesThatUses()`. `Bunch::fromExtends()`, `fromImplements()` and
`fromUses()` wrap that and instantiate. This is why adding a file is enough to register a
controller, a command, a migration, a query builder, a migration plan or a channel.

**Capability self-declaration over central dispatch.** Backends answer for themselves:
`QueryBuilder::supports($driver)`, `Migration\Plan::support($driver)`,
`ModelGenerator\Adapters\DatabaseAdapter::supports($driver)`, `WebAPI::handle($request)`. The
selector loops over candidates and asks — there is no map from key to class to update.

**Configuration is typed objects.** `cube.php` returns a list of `ConfigurationElement`
instances (`Applications`, `DatabaseConfiguration`, `RouterConfiguration`,
`AutoloaderConfiguration`, `CacheConfiguration`, `Import`, `GenericElement`, …). Each subsystem
reads its own with `MyConfiguration::resolve()`, whose fallback chain is: element found in the
`Configuration` component → provided default → `new static()`. Config objects hold ready-made
collaborators (a `LocalDiskCache`, a `StaticServer`), not strings to interpret later.
They get serialized by `configuration:cache`, so **no closures inside a configuration element**.

**Memoization invalidated on deployment.** The class index and the applications file lists are
cached under fixed keys (`AutoloaderConfiguration(cached: true)`, on by default in production),
snapshotted into APCu when available, and the configuration has its own cache identified by name.
`Autoloader::cleanCache()` listens to `PostDeployment` and drops both copies (the CLI cannot reach
php-fpm's APCu, a php-fpm restart does). Invalidate the configuration with
`php do configuration:cache`.

## Applications

`new Applications('App')` declares a module directory. Inside it, directory names carry meaning:

| Directory | Consumed as |
|---|---|
| `Routes/`, `Router/` | route files, required by the `Router` with `$router` in scope |
| `Assets/` | asset files (`AssetsInserter`, `AssetServer`) |
| `Requires/`, `Includes/`, `Helpers/`, `Schedules/`, `Cron/` | included at boot (global functions, `Cube\schedule(...)` calls) |
| `Views/` | views for `Renderer` / `Cube\render()` |

Everything else (`Models/`, `Controllers/`, `Commands/`, `Migrations/`, `Queue/`, `Channels/`) is
found by class discovery, not by path. `tests/integration-root/App` is the reference example.

## Data layer

- **`Bunch`** is the fluent collection used everywhere internally (`of`, `map`, `filter`,
  `reduce`, `first`, `groupBy`, `key`, `zip`, `partitionFilter`, …), generic over `<TKey,TValue>`
  in PHPDoc. Prefer it to raw `array_*` chains in framework code.
- **`Query` is an intermediate representation**, never a string built as you go: `Query\Field`,
  `FieldCondition`, `Join`, `Order`, `Limit`, `InsertValues`… are value objects rendered by the
  `Builders\QueryBuilder` matching the connection driver (MySQL, Postgres, SQLite). A new DBMS
  costs one builder class. `Query::with()` expands a relation tree into joins.
- **`Model` is a descriptor**: `table()`, `fields()` (a map of `ModelField`), `relations()`.
  `ModelField` is the single description projected into everything else — the migration schema
  (`Migration\Plan`), the validation rule (`toRule()`, `Model::toObjectParam()`), the generated
  TypeScript types (`models:to-types`), the OpenAPI schema. Add a consumer, not a copy.
- Model data lives in `$model->data` (with `$model->original` for change tracking); `__get`/`__set`
  are the one deliberate use of magic, compensated by generated `@property` docblocks.
  Relations are `HasOne`/`HasMany` objects stored in `$model->references`, loaded via
  `load()`/`loadMissing()`/`RelationTree`.
- `models:generate` regenerates model classes from the live database. Everything it owns carries
  `#[Generated]`; methods **without** that attribute and existing `use` statements are preserved
  across regeneration.
- Migrations: `up(Plan $plan, Database $database)` written against the abstract `Plan` API, always
  dry-run first (`DryRunPlan`) then executed in a transaction.

## Validation

`Http\Rules` is a step pipeline: a `Rule` is a list of `ValidationStep` checkers and transformers,
`Param::integer()`, `Param::email()`, `Param::object()`, `Param::array()`, `Param::anyKeyObject()`
compose them, and `validate()` returns a `ValidationReturn` (errors keyed by path + coerced
result). Composites (`ObjectParam`, `ArrayParam`) recurse and aggregate errors by key.
Declare the shape in a `Request` subclass's `getRules()`; the router validates before the
controller body runs, so a controller never re-checks input.

## Tooling

- **CLI**: `php do <scope>:<name>`; `Command` derives both from the class name and namespace
  (`Commands\Cache\Clear` → `cache:clear`), `Args` parses argv. `execute(Args $args): int`.
- **Tests**: `tests/units` (plain `PHPUnit\Framework\TestCase`, namespace `Cube\Tests\Units\*`),
  `tests/integration` driving the app in `tests/integration-root`, `tests/apcu` against the
  containerized nginx+APCu server. `Test\CubeTestCase` resets the database per test and gives
  `get()/post()/getJson()/...` returning a fluent `ResponseAssert`.
- **Docs**: `docs/` holds numbered markdown pages; the previous/next menus are generated by
  `php docs/generate-menus.php` (run from `docs/`) — never hand-write them.
