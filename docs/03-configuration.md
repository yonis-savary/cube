<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./02-installation.md">Previous : Installation</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./04-applications-and-discovery.md">Next : Applications and discovery</a></div></td></tr></table>

# Configuration

Your application and Cube are configured in `cube.php`, a file at the root of your project returning a
list of typed configuration elements, held by the `Configuration` component. Each subsystem owns one
element class : your IDE lists its options, there is no string key to guess.

Here is an example

```php
return [
    // The application directories to load
    new Applications('App'),

    new DatabaseConfiguration(
        driver: 'mysql',
        database: env('DB_NAME'),
        host: env('DB_HOST'),
        port: (int) env('DB_PORT', 3306),
        user: env('DB_USER'),
        password: env('DB_PASSWORD'),
    ),

    new RouterConfiguration(
        apis: [new StaticServer('FrontEnd/Public')],
        securityHeaders: ['X-Frame-Options' => 'DENY'],
    ),

    // The elements of another file
    new Import('Config/production.php'),
];
```

An element you leave out keeps its defaults : a fresh project only needs `new Applications('App')`.

## Reading an element

A subsystem reads its element with `resolve()`. Here is an example

```php
$database = DatabaseConfiguration::resolve();
$database->driver; // 'mysql'
```

`resolve()` returns the first of these that exists

| Step | Returns |
|---|---|
| the element is in your configuration | that element |
| a default is passed : `resolve(default: new DatabaseConfiguration('sqlite'))` | the given default |
| otherwise | `new static()`, the element with its constructor defaults |

Its first parameter is the `Configuration` to read from, the global one when `null` : pass your own
`new Configuration(...$elements)` to read from another set, in a test for example.

A class receiving an element in its constructor gets it through `resolve()` : you rarely call it
yourself (see [Dependency injection](./05-dependency-injection.md)).

```php
class ProductImporter
{
    public function __construct(
        protected DatabaseConfiguration $database, // DatabaseConfiguration::resolve()
    ) {}
}
```

## One element per class

The configuration keeps one element per class : a second `DatabaseConfiguration` replaces the first
one. Elements are added in the order they are listed, so the last one wins.

```php
return [
    new DatabaseConfiguration('sqlite', 'database.sqlite'),

    // Replaces the sqlite configuration when Config/production.php declares a DatabaseConfiguration
    new Import('Config/production.php'),
];
```

## Splitting the configuration with `Import`

`Import` adds the elements of another file, at the position of the `Import` in the list. Here is an
example

```php
// Config/production.php
return [
    new AutoloaderConfiguration(cached: true),
    new CacheConfiguration(new RedisCache('shop', env('REDIS_HOST'))),
];
```

- The path is relative to your project, or absolute.
- The file returns an array of elements, or a single element. It can itself contain an `Import`.
- A missing file, or a file returning anything else, does not fail : it logs a warning and adds
  nothing. Check your logs when an imported setting seems ignored.

## Reading the `.env` file

`Cube\env($key, $default)` reads a value of your `.env` file (see
[Installation](./02-installation.md#the-env-file)). Call it from your configuration files only

```php
new DatabaseConfiguration('sqlite', env('DB_FILENAME', 'database.sqlite')),
```

Once the configuration is cached, `cube.php` is no longer read : the values `env()` returned are
stored inside the elements. Reading a setting through an element everywhere else keeps a single
source for it.

## Your own settings

Give your application settings their own element rather than reading `env()` here and there. Here is
an example

```php
class BillingConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $currency = 'EUR',
        public readonly float $vatRate = 0.2,
        public readonly ?string $stripeKey = null,
    ) {}
}

// cube.php
new BillingConfiguration(stripeKey: env('STRIPE_KEY')),

// anywhere
$vatRate = BillingConfiguration::resolve()->vatRate;
```

- Give **every constructor parameter a default** : without an element in `cube.php`, `resolve()`
  builds `new static()`. An element with a required parameter throws a `RuntimeException` asking to
  declare it in `cube.php` instead.
- Hold **ready-made collaborators** rather than strings someone must interpret later : Cube's own
  elements take a cache driver, a session driver or an authentication provider as an object.
- **No closure and no resource** inside an element : `configuration:cache` serializes them.

```php
// A collaborator, built once in cube.php
new CacheConfiguration(new RedisCache('shop', env('REDIS_HOST'), password: env('REDIS_PASSWORD'))),
new AuthenticationConfiguration(new PasswordAuthentication(User::class, 'login', 'password')),
```

## Untyped values with `GenericElement`

`GenericElement` stores a value under a name. Read it with `resolveGeneric()`, which returns the
given default when the name is unknown

```php
// cube.php
new GenericElement('billing.currency', 'EUR'),

// anywhere
$currency = Configuration::getInstance()->resolveGeneric('billing.currency', 'USD');
```

A second `GenericElement` with the same name replaces the first one. Prefer a typed element : your
IDE cannot help with a name.

## Caching the configuration

`configuration:cache` stores every element of your configuration, so that `cube.php` and its imports
are no longer included on each request

```sh
php do configuration:cache
```

From then on, **`cube.php` is ignored** : a change to `cube.php`, to an imported file or to a `.env`
value used in them only applies after running `configuration:cache` again. The `PostDeployment` event
drops the cache, so that a release reads `cube.php` again (see [Deployment](./34-deployment.md)) ;
`cache:clear` does not, as the cache lives apart under `Storage/cube/configuration`.

The cache holds your elements as they are, passwords included, and a `RedisSession` puts its
password in `session.save_path`, which `phpinfo()` shows : keep `Storage` outside your web root and
readable by your application only. See [Deployment](./34-deployment.md) for the production checklist.

## Built-in elements

| Element | Options (defaults) | Configures |
|---|---|---|
| `Applications(...$paths)` | `'App'` when no path is given | the application directories to load, see [Applications and discovery](./04-applications-and-discovery.md) |
| `AutoloaderConfiguration` | `cached` (`isProduction()`), `displayErrors` (`isDebug()`) | the cache of class discovery and PHP error display, see [Deployment](./34-deployment.md) |
| `DatabaseConfiguration` | `driver` (`'sqlite'`), `database` (`'db.sqlite'`), `host`, `port`, `user`, `password` | the SQL connection : `sqlite`, `mysql`, `pgsql`, see [Database and queries](./16-database-and-queries.md) |
| `MigrationManagerConfiguration` | `tableName` (`'__cube_migration'`), `directoryName` (`'Migrations'`) | the table recording migrations and their directory in each application, see [Migrations](./17-migrations.md) |
| `RouterConfiguration` | `cached` (`false`), `loadControllers` (`true`), `loadRoutesFiles` (`true`), `apis` (`[]`), `commonMiddlewares` (`[]`), `commonPrefix` (`'/'`), `securityHeaders` (`[]`) | routing, global middlewares and extra `WebAPI`s, see [Routing and middlewares](./11-routing-and-middlewares.md) |
| `CORSConfiguration` | `allowedOrigins` (`CORS_ALLOWED_ORIGINS`), `allowHeaders` (`CORS_ALLOWED_HEADERS`), `allowCredentials` (`CORS_ALLOWED_CREDENTIALS`), `maxAge` (`86400`) | CORS answers, see [Routing and middlewares](./11-routing-and-middlewares.md) |
| `CacheConfiguration` | `driver` (a `LocalDiskCache` in `Storage/Cache`) | the default `Cache` driver, see [Storage and caching](./21-storage-and-caching.md) |
| `SessionConfiguration` | `namespace` (`'cube'`), `driver` (a `PHPGlobalSession`) | the session driver, see [Sessions](./22-sessions.md) |
| `AuthenticationConfiguration` | `provider`, **required** | the `Authentication` provider, see [Authentication](./23-authentication.md) |
| `UserRegisterConfiguration` | `cookieName` (`'remember-me-token'`), `cookieDuration` (two weeks), `refreshTokenOnRemember` (`true`), `cookieSecure` (`true`), `cookieHttpOnly` (`true`), `cookiePath` (`'/'`), `cookieSameSite` (`'Lax'`) | the remember-me cookie, see [Remember me](./25-remember-me.md) |
| `OpenAPIConfiguration` | `outputFile` (`Storage/openapi.json`), `title` (`'Application'`), `version` (`'0.0.1'`), `authenticationScheme`, `jsonFlags`, `displayLogs` (`true`) | the generated OpenAPI document, see [API documentation](./15-api-documentation.md) |
| `ToTypesConfiguration` | `outputFile` (`Storage/cube-types.ts`) | the output of `models:to-types`, see [Models](./18-models.md) |
| `LargeUploadManagerConfiguration` | `storageName` (`'/large-upload-temp'`), `maxSize` (none) | chunked uploads, see [Controllers and responses](./13-controllers-and-responses.md) |
| `WebsocketConfiguration` | `websocketHost` (`'0.0.0.0'`), `websocketPort` (`8088`), `httpHost` (`'127.0.0.1'`), `httpPort` (`8089`), `broadcastSecret`, `allowedOrigins` (the CORS ones) | the websocket server, see [Websockets](./30-websockets.md) |
| `BroadcastConfiguration` | `socketHost`, `socketPort`, `httpHost`, `httpPort`, `broadcastSecret` (the `WebsocketConfiguration` ones) | the client broadcasting to the websocket server, see [Websockets](./30-websockets.md) |
| `Import($fileToImport)` | | the elements of another file, see [above](#splitting-the-configuration-with-import) |
| `GenericElement($name, $value)` | | an untyped value, see [above](#untyped-values-with-genericelement) |

`AuthenticationConfiguration` is the only element without a default for every option : declare it
before using `Authentication`, otherwise building the component throws a `RuntimeException` naming
the missing element.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./02-installation.md">Previous : Installation</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./04-applications-and-discovery.md">Next : Applications and discovery</a></div></td></tr></table>
