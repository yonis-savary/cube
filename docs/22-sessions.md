<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./21-storage-and-caching.md">Previous : Storage and caching</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./23-authentication.md">Next : Authentication</a></div></td></tr></table>

# Sessions

The `Session` component stores values for the current visitor between two requests. It is a small
key-value API over a driver : PHP's native sessions by default, Redis or your database when you
configure them.

```php
$session = Session::getInstance();

$session->set('cart', [12, 47]);
$session->get('cart');              // [12, 47]
$session->get('currency', 'EUR');   // 'EUR', the key is not set
$session->has('cart');              // true
$session->unset('cart', 'currency');
```

The session starts the first time a `Session` is built : you never call `session_start()` yourself.

## Namespaces

Every `Session` reads and writes inside its own namespace, so two parts of your application can use
the same key without overwriting each other. `Session::getInstance()` uses the namespace of your
`SessionConfiguration` (`cube` by default). Build another `Session` to work in another one

```php
$backOffice = new Session(SessionConfiguration::resolve(), 'back-office');

$backOffice->set('cart', 'back office content');
Session::getInstance()->get('cart');    // untouched by the line above
```

`clear()` empties the namespace of the session you call it on, and nothing else. An empty namespace
throws an `InvalidArgumentException`.

With the native drivers, a namespace lives under `$_SESSION['cube'][<namespace>]`. Leave the rest of
`$_SESSION` to code that does not go through Cube.

## A session of its own for a service

`HasScopedSession` gives a class a private namespace, derived from its class name, through a
`session()` method built on first use. This is how `Authentication` keeps the logged-in user away
from your own keys.

```php
class CartService
{
    use HasScopedSession;

    public function add(Product $product): void
    {
        $products = $this->session()->get('products', []);
        $products[] = $product->id;

        $this->session()->set('products', $products);
    }
}
```

| Method of `HasScopedSession` | Does |
|---|---|
| `session()` | the `Session` of this class, built once per instance |
| `getScope()` | the namespace, an MD5 of the class name. Override it to share a namespace between classes |
| `getSessionConfiguration()` | the configuration used, `SessionConfiguration::resolve()` by default. Override it to give this class another driver |

## One value, one class : `Straw`

A `Straw` is a class standing for one session value. Its key is the MD5 of the class name, so no
string key is repeated around your code, and your IDE finds every use of it.

```php
class LastVisitedProduct extends Straw {}

LastVisitedProduct::set($product->id);
LastVisitedProduct::get();     // null when never set
LastVisitedProduct::unset();
```

The three methods use `Session::getInstance()`, and take another `Session` as their last argument.
Override the static `getKey()` if you want a readable key instead.

## Choosing a driver

Declare the driver in your `cube.php`

```php
new SessionConfiguration(
    namespace: 'cube',
    driver: new RedisSession(),
)
```

| Driver | Stores sessions | Arguments |
|---|---|---|
| `PHPGlobalSession` | wherever PHP is configured to (`session.save_handler`, files by default) | `sameSite`, `secureCookie` |
| `RedisSession` | in Redis, through the session handler of the `phpredis` extension | `host`, `port`, `sameSite`, `secureCookie`, `password` |
| `DatabaseSession` | in a table of your database | `table`, `sameSite`, `database`, `secureCookie` |

### Redis

`RedisSession` needs the `phpredis` extension. Its `host` defaults to the `SESSION_REDIS_HOST`
environment variable (`redis` when unset), its `port` to `6379`, and its `password` to
`SESSION_REDIS_PASSWORD`

```php
new SessionConfiguration(driver: new RedisSession(
    host: env('SESSION_REDIS_HOST'),
    port: 6380,
    password: env('SESSION_REDIS_PASSWORD'),
))
```

The password goes to Redis through PHP's `session.save_path` (`tcp://host:port?auth=...`), which
`phpinfo()` displays : never expose a `phpinfo()` page on a server using it.

### Database

`DatabaseSession` keeps each session in a row of the `__cube_session` table, which it creates the first
time a session is opened. It uses `Database::getInstance()` unless you pass a `database`

```php
new SessionConfiguration(driver: new DatabaseSession(table: 'app_session'))
```

A row older than PHP's `session.gc_maxlifetime` is refused when read, even when PHP's garbage
collector never runs to delete it (`session.gc_probability=0`, the Debian default).

## Cookie and security

Whatever the driver, the session cookie is

- named after an MD5 of your project path, so two Cube applications on the same domain do not share it
- `HttpOnly`
- `Secure` over HTTPS, detected from `$_SERVER['HTTPS']` or, behind a proxy, from an
  `X-Forwarded-Proto: https` header
- `SameSite=Lax`

Change the last two through the driver

```php
// force Secure when the proxy in front of you sends no X-Forwarded-Proto
new SessionConfiguration(driver: new PHPGlobalSession(sameSite: 'Strict', secureCookie: true))
```

Cube turns on PHP's `session.use_strict_mode`, so a session id the server never issued is replaced
rather than adopted. `regenerateId()` gives the session a new id and keeps its values :
`Authentication` calls it on every login and logout. Once output has started, the id can no longer
change : Cube keeps it and logs a warning naming the file and line that printed.

How long a session lives is PHP's business : set `session.gc_maxlifetime` and
`session.cookie_lifetime` in your PHP configuration.

## Writing your own driver

Implement `SessionDriverInterface`, then pass an instance to `SessionConfiguration`. Each `Session`
works on a clone of the configured driver, then calls `initialize()` with its namespace. Here is a
driver keeping values for the current process only, handy in tests

```php
class MemorySession implements SessionDriverInterface
{
    protected array $values = [];
    protected string $namespace;

    public function initialize(string $namespace) { $this->namespace = $namespace; }
    public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
    public function set(string $key, mixed $value) { $this->values[$key] = $value; }
    public function has(string $key): bool { return array_key_exists($key, $this->values); }
    public function delete(string $key): void { unset($this->values[$key]); }
    public function clear(): void { $this->values = []; }
    public function regenerate() {}
}
```

The driver is part of your configuration, which `configuration:cache` serializes : keep it free of
closures. See [Configuration](./03-configuration.md#caching-the-configuration).

## Reference

| Method of `Session` | Does |
|---|---|
| `get(string $key, mixed $default = null)` | the value, `$default` when the key is not set |
| `set(string $key, mixed $value)` | stores the value |
| `has(string $key)` | whether the key is set, even to `null` |
| `unset(string ...$keys)` | removes every given key |
| `clear()` | empties this namespace |
| `regenerateId()` | gives the session a new id, values kept |

| Option of `SessionConfiguration` | Default | Does |
|---|---|---|
| `namespace` | `cube` | namespace of `Session::getInstance()` |
| `driver` | `new PHPGlobalSession()` | where sessions are stored |

| Environment variable | Read by |
|---|---|
| `SESSION_REDIS_HOST` | `RedisSession`, when no `host` is given |
| `SESSION_REDIS_PASSWORD` | `RedisSession`, when no `password` is given |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./21-storage-and-caching.md">Previous : Storage and caching</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./23-authentication.md">Next : Authentication</a></div></td></tr></table>
