<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./107-controller-and-apis.md">Previous : Controller and apis</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./109-events.md">Next : Events</a></div></td></tr></table>

# Storage and Caching

`Storage` is a filesystem rooted at one directory : every path you give it is resolved inside that
root, so a component can be handed a directory without being able to wander out of it. `Cache` is a
keyed store with a lifetime, backed by a driver.

```php
Storage::getInstance()->write('invoices/2026-01.pdf', $bytes);

$rates = Cache::getInstance()->getOrSet('exchange-rates', fn () => $api->fetchRates(), Cache::HOUR);
```

## Storage

The default instance points at the `Storage/` directory of your project. Ask it for a path rather
than building one with `__DIR__` — that is the whole point of the component.

```php
$storage = Storage::getInstance();

$storage->write('report.csv', $content);
$content = $storage->read('report.csv');
$absolute = $storage->path('report.csv');   // to hand to something outside Cube
```

| Method | Does |
|---|---|
| `path(string $path)` | resolves a path inside the root, without touching the disk |
| `getRoot()` | the root directory itself |
| `write(string $path, string $content, int $flags = 0)` | writes a file, `$flags` goes to `file_put_contents` |
| `read(string $path)` | reads a file |
| `unlink(string $path)` | removes a file |
| `makeDirectory(string $path, bool $recursive = true)` | creates a directory |
| `exists()` / `isFile()` / `isDirectory()` | what is at that path |
| `isReadable()` / `isWritable()` | whether you may act on it |
| `files()` / `directories()` / `scanDirectory()` | one level down |
| `exploreFiles()` / `exploreDirectories()` / `explore()` | recursively |

### Scoping to a subdirectory

`child()` gives you another `Storage` rooted deeper, and `parent()` goes back up. This is how you
hand a subsystem exactly the directory it is allowed to touch.

```php
$logs = Storage::getInstance()->child('Logs');

$logs->write('audit.csv', $line);        // Storage/Logs/audit.csv
$logs->files();                          // only what is under Logs/
```

A `Storage` is `Stringable` : casting it to a string gives its root, which is convenient in log
messages.

### Another backend

`Storage` delegates to a `StorageDriver` — `LocalDiskDriver` by default. Extend `StorageDriver`,
implement its methods, and pass an instance as the second constructor argument.

```php
new Storage('/', new LocalDiskDriver());
```

## Cache

The default instance stores its entries in `Storage/Cache`. Values are serialized, so anything that
survives `serialize()` can be cached.

```php
$cache = Cache::getInstance();

$cache->set('user-count', 1240, Cache::DAY);
$count = $cache->get('user-count', 0);      // 0 when absent or expired
```

`getOrSet()` is the form you will use most : it computes the value only when the key is missing, and
a callable is invoked rather than stored.

```php
$rates = $cache->getOrSet('exchange-rates', fn () => $api->fetchRates(), Cache::HOUR);
```

| Method | Does |
|---|---|
| `get(string $key, mixed $default = null)` | reads, `$default` when missing |
| `set(string $key, mixed $value, int $timeToLive = Cache::MONTH)` | writes, returns the stored value |
| `getOrSet(string $key, mixed $value, int $timeToLive = Cache::MONTH)` | reads, computing and writing on a miss |
| `has(string $key)` | whether the key is there and alive |
| `try(string $key)` | reads, `false` when missing |
| `delete(string $key)` | removes one entry |
| `clear()` | empties the whole cache |
| `getReference(string $key, mixed $default)` | a mutable slot you can write through |

Lifetimes are seconds, and the constants save you the arithmetic

| Constant | Value |
|---|---|
| `Cache::PERMANENT` | never expires |
| `Cache::SECOND` | 1 |
| `Cache::MINUTE` | 60 |
| `Cache::HOUR` | 3600 |
| `Cache::DAY` | 86400 |
| `Cache::WEEK` | 7 days |
| `Cache::MONTH` | 31 days, the default |

### Namespacing keys

`child()` returns a cache whose keys are all prefixed, so two subsystems cannot collide.

```php
$reports = Cache::getInstance()->child('reports');

$reports->set('daily', $rows);   // stored as reports-daily
```

One caveat worth knowing : on a prefixed cache, `getOrSet()`, `try()` and `getReference()` prefix the
key twice, so a value written through them is not found again by `get()` or `has()`. Until that is
fixed, use `set()` and `get()` on a `child()` cache, or keep `getOrSet()` for the root one.

### Drivers

| Driver | Stores in |
|---|---|
| `LocalDiskCache` | a `Storage` directory, `Storage/Cache` by default |
| `RedisCache` | a Redis server |

Declare the one you want with a `CacheConfiguration` in your `cube.php`

```php
new CacheConfiguration(
    new RedisCache('my-app', env('CACHE_REDIS_HOST', 'redis'), 6379)
)
```

`RedisCache` takes an identifier that namespaces every key as `cache:<identifier>:<key>`, a host —
falling back to the `CACHE_REDIS_HOST` environment variable — and a port. `LocalDiskCache` takes an
optional `Storage`, so you can point it at any directory

```php
new CacheConfiguration(
    new LocalDiskCache(Storage::getInstance()->child('MyCache'))
)
```

A `CacheConfiguration` is serialized when you run `configuration:cache`, so the driver it holds must
be serializable — do not put a closure in it.

### Emptying it

```sh
php do cache:clear
```
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./107-controller-and-apis.md">Previous : Controller and apis</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./109-events.md">Next : Events</a></div></td></tr></table>
