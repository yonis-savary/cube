<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./20-data-transfer-objects.md">Previous : Data transfer objects</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./22-sessions.md">Next : Sessions</a></div></td></tr></table>

# Storage and caching

`Storage` is a filesystem rooted at one directory : every path you give it is resolved inside that
root, so a component can be handed a directory without being able to wander out of it. `Cache` is a
keyed store with a lifetime, backed by a driver.

```php
Storage::getInstance()->child('invoices')->write('2026-01.pdf', $bytes);

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

A path is always resolved inside the root : a leading `/` means the root itself, and `..` never
climbs above it, so a path taken from user input cannot reach the rest of the disk

```php
$storage->path('../../etc/passwd');   // <project>/Storage/etc/passwd
```

`write()` does not create missing directories : create them with `makeDirectory()`, or write through
a `child()`, which creates its root. It returns `false` and logs a warning when the file cannot be
written.

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
| `files()` / `directories()` | absolute paths, one level down |
| `scanDirectory()` | names, one level down |
| `exploreFiles()` / `exploreDirectories()` / `explore()` | absolute paths, recursively |

The listing methods take a path too, the root by default.

### Scoping to a subdirectory

`child()` gives you another `Storage` rooted deeper — creating that directory — and `parent()` goes
back up. This is how you hand a subsystem exactly the directory it is allowed to touch.

```php
$logs = Storage::getInstance()->child('Logs');

$logs->write('audit.csv', $line);        // Storage/Logs/audit.csv
$logs->files();                          // only what is under Logs/
```

A `Storage` is `Stringable` : casting it to a string gives its root, which is convenient in log
messages.

### Another backend

`Storage` delegates to a `StorageDriver` — `LocalDiskDriver` by default. Extend `StorageDriver`,
implement its abstract methods, and pass an instance as the second constructor argument.

```php
new Storage('/srv/shared', new LocalDiskDriver());
```

## Cache

The default instance stores its entries in `Storage/Cache`. Values are serialized, so anything that
survives `serialize()` can be cached.

```php
$cache = Cache::getInstance();

$cache->set('user-count', 1240, Cache::DAY);
$count = $cache->get('user-count', 0);      // 0 when absent or expired
```

`getOrSet()` is the form you use most : it computes the value only when the key is missing. A
`Closure` given to `getOrSet()` or `set()` is called and its result stored — any other value, an
invokable object included, is stored as it is.

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
| `clear()` | empties the whole cache, returns what it removed |
| `getReference(string $key, mixed $default)` | a mutable slot you can write through |
| `child(string $prefix)` | the same cache, with prefixed keys |

`set()` and `getOrSet()` also take a creation timestamp as a fourth argument, `time()` by default,
which the lifetime counts from.

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

### Writing through a reference

`getReference()` hands you the stored value itself : what you change through it is what the cache
holds, without calling `set()` again

```php
$counter = &Cache::getInstance()->getReference('page-views', 0);
$counter++;
```

A `RedisCache` cannot do this : it logs a warning and returns a copy.

### Namespacing keys

`child()` returns a cache whose keys are all prefixed, so two subsystems cannot collide.

```php
$reports = Cache::getInstance()->child('reports');

$reports->set('daily', $rows);   // stored as reports-daily
$reports->getOrSet('weekly', fn () => $this->buildWeekly());
```

`clear()` empties the whole driver, not only the keys of a `child()`.

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

`RedisCache(string $identifier = 'cube', ?string $host = null, int $port = 6379, ?string $password = null)`
namespaces every key as `cache:<identifier>:<key>`, the identifier lowercased and any character other
than a letter or a digit turned into a `.`. The host falls back to the `CACHE_REDIS_HOST`
environment variable, then to `redis` ; the password falls back to `CACHE_REDIS_PASSWORD` — set one
in production. Cached values are unserialized, so `RedisCache` signs each one with your `APP_KEY`
and ignores (with a warning) a value it did not sign : `APP_KEY` must be set to use it, and changing
it empties the cache in effect.

`LocalDiskCache` keeps its entries in memory and writes them to disk when the cache is destroyed,
at the end of the request or the command : one file per entry, named after its creation date,
lifetime and key. It takes an optional `Storage`, so you can point it at any directory

```php
new CacheConfiguration(
    new LocalDiskCache(Storage::getInstance()->child('MyCache'))
)
```

A `CacheConfiguration` is serialized when you run `configuration:cache`, so the driver it holds must
be serializable — do not put a closure in it. See [Configuration](./03-configuration.md).

### Emptying it

```sh
php do cache:clear
```

It empties the cache through its driver, whichever it is, and lists what it removed : the files of a
`LocalDiskCache`, the keys of a `RedisCache`. `Cache::clear()` returns the same list.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./20-data-transfer-objects.md">Previous : Data transfer objects</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./22-sessions.md">Next : Sessions</a></div></td></tr></table>
