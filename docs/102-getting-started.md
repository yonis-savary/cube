<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./101-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./103-applications.md">Next : Applications</a></div></td></tr></table>

# Getting started

## Setting up a Cube project

First, install Cube dependency with `composer`

```sh
composer require yonis-savary/cube
```

Then, copy the server files

```sh
cp -r vendor/yonis-savary/cube/server/. .
```

Server files are utilities such as the `Public/index.php` entrypoint, `do` script (used to launch commands)...etc. Files that are at the root directory of your project.
The trailing `/.` also copies `.gitignore` and `.dockerignore`, which keep your `.env` out of git and
out of the Docker image : `docker-compose.yml` mounts it into the container instead, so create it
before the first `docker compose up` (Docker creates a directory in place of a missing file).

## Configuration / Env

Your application and frameworks are configured through `cube.php`, which is a file returning an array of configuration elements

The goal here is to allow anyone to configure Cube through strong typed configuration elements, your IDE will help you out !

Here is an example of configuration

```php
return [
    # Applications to load
    new Applications('App'),

    new DatabaseConfiguration(
        "sqlite",
        env("DB_FILENAME", "app.sqlite")
    ),

    new RouterConfiguration(
        apis: [AssetServer::class]
    )

    # You can also import some configuration from another file
    new Import('Config/production.php')
];
```

The `env` key of your `.env` (or `environment`, read when `env` is missing) names the environment

```ini
env=debug
```

| Value | `Cube\isProduction()` | `Cube\isDebug()` | An uncaught exception answers |
|---|---|---|---|
| starts with `prod` | `true` | `false` | `Internal Server Error` |
| `debug`, `dev`, `development`, `local`, `test`, `testing` | `false` | `true` | the message and the trace |
| anything else (`staging`, `preprod`...) | `false` | `false` | `Internal Server Error` |
| no key | `false` | `false` | `Internal Server Error` |

`isProduction()` is also the default of `AutoloaderConfiguration(cached:)`, which caches the class
index and the applications files and, when APCu is available, snapshots them there too. This cache
does not notice a new controller or route file by itself : dispatch `PostDeployment` after each
deployment (`php do cube:dispatch --event PostDeployment`) to empty it, and restart php-fpm so its
APCu copy goes away as well. APCu is shared by every pool of one php-fpm master, and the snapshot
holds the files included at boot : give each application its own php-fpm master.

`isDebug()` is the default of `AutoloaderConfiguration(displayErrors:)` : outside a debug environment,
PHP warnings and notices go to the error log instead of being printed into the response, absolute
paths included.

Values are read as written, so a base64 value ending in `==` or `PASSWORD=abc!def` need no quotes. `true`,
`on`, `yes` read as `"1"`, and `false`, `off`, `no`, `none`, `null` as `""`. Variables of the process
(`getenv()` and `$_ENV`) are read first, and the `.env` file overrides them.

`APP_KEY` signs the Redis cache and queue payloads and keys `Cube\encrypt()` : it must hold at least
32 bytes, and anything shorter throws. `php do key:generate` prints a random one to paste in your
`.env`.

`configuration:cache` writes your configuration elements, passwords included, under
`Storage/cube/configuration`, and a `RedisSession` puts its password in `session.save_path`, which
`phpinfo()` shows. Keep `Storage` outside your web root and readable by your application only.

## Components

Cube components are classes that uses the `Component` trait. This trait
is a simple way to implement singleton through the app

Here is an example of a simple component

```php
class Multiplier
{
    use Component;

    public static function getDefaultInstance()
    {
        return new self(5);
    }

    public function __construct(
        protected int $baseFactor=1
    ){}

    public function multiply(int $number)
    {
        return $this->number * $this->baseFactor;
    }
}
```

And how to use it

```php
$global = Multiplier::getInstance();
$global->multiplty(5); // 25
$global->multiplty(2); // 10


$doubler = new Multiplier(2);
$doubler->multiply(50); // 100
Multiplier::setInstance($doubler); // Replace the global instance

$tripler = new Multiplier(3);

$tripler->asGlobalInstance(function(){
    $global = Multiplier::getInstance(); // Return $tripler only in this function
});
```

Most notable components are
- `Router`
- `Configuration`
- `Logger`
- `Database`
- `Authentication`

### Running code with fresh components

`RequestContext::oneShot()` runs a callback where every component starts from scratch, then gives
each component back the instance it had before. Components created inside the callback do not
survive it.

```php
$database = Database::getInstance();

$report = RequestContext::oneShot(function (RequestContext $context) {
    // Database::getInstance() builds a new instance here
    return Product::select()->fetch();
});

Database::getInstance(); // $database again
```

The second parameter lists the components the callback shares with the outside, untouched

```php
RequestContext::oneShot(fn () => $this->handle($message), [Configuration::class, Logger::class]);
```

The callback receives the context, which is also the global `RequestContext` while it runs. It returns
whatever the callback returns.

`oneShot()` scans your classes to know every component each time it is called. To run many callbacks
with the same persistent components, build the context once and call `run()` on it

```php
$context = new RequestContext([Configuration::class, Logger::class]);

foreach ($messages as $message)
    $context->run(fn () => $this->handle($message));
```

Static properties are not components : they are left as they are.


<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./101-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./103-applications.md">Next : Applications</a></div></td></tr></table>
