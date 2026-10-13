<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./01-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./03-configuration.md">Next : Configuration</a></div></td></tr></table>

# Installation

Cube is a composer library (`yonis-savary/cube`, PHP 8.5 or later) : you install it, copy its server
files at the root of your project, and declare your application in `cube.php`.

Here is the whole setup

```sh
composer require yonis-savary/cube

# The trailing /. also copies the hidden files (.gitignore, .dockerignore)
cp -r vendor/yonis-savary/cube/server/. .

mkdir App
php do key:generate   # prints an APP_KEY line to paste in your .env
```

Then let composer autoload your application and your tests, in `composer.json`

```json
{
    "autoload": {
        "psr-4": { "App\\": "App/" }
    },
    "autoload-dev": {
        "psr-4": { "Tests\\": "Tests/" }
    }
}
```

```sh
composer dump-autoload
```

Cube finds your classes through composer's mapping : a class in a directory composer does not
autoload is never discovered (see [Applications and discovery](./04-applications-and-discovery.md#class-discovery)).

## The server files

The `server/` directory of Cube holds the files that live at the root of your project.

| File | Role |
|---|---|
| `Public/index.php` | the web entry point : boots Cube, routes the request, sends the response |
| `do` | the command launcher : `php do <command>`, see [Commands](./10-commands.md) |
| `cube.php` | your configuration, see [Configuration](./03-configuration.md) |
| `Makefile` | `make build`, `make up`, `make down` and `make deploy` around docker compose |
| `docker-compose.yml`, `Docker/Dockerfile.php`, `Docker/vhost.conf` | a php-fpm (with APCu) + nginx stack serving your application |
| `.gitignore`, `.dockerignore` | keep `vendor/`, `Storage/` and `.env` out of git and out of the Docker image |
| `phpunit.xml`, `Tests/bootstrap.php`, `Tests/Unit/HelloWorldTest.php` | a PHPUnit setup with a temporary storage and database, see [Testing](./33-testing.md) |
| `AGENTS.md`, `CLAUDE.md` | instructions for coding agents, pointing them to `vendor/yonis-savary/cube/ai/FRAMEWORK.md` |

`AGENTS.md` ends with a *Project rules* section : add the rules specific to your application there.
`CLAUDE.md` only includes `AGENTS.md`.

## The `.env` file

The `.env` file at the root of your project holds the values that change from one machine to another.
Read them with `Cube\env()`, from your configuration files only (see
[Configuration](./03-configuration.md#reading-the-env-file)).

```ini
# Lines starting with # are comments
env=debug
# Printed by php do key:generate
APP_KEY=...
DB_HOST=mysql
DB_PASSWORD=abc!def
```

Values are read as written, so a base64 value ending in `==` or `PASSWORD=abc!def` need no quotes.

| Written | Read as |
|---|---|
| `true`, `on`, `yes` | `"1"` |
| `false`, `off`, `no`, `none`, `null` | `""` |
| anything else | the string as written |

Variables of the process (`getenv()` and `$_ENV`) are read first, and the `.env` file overrides them.
A missing or unparsable `.env` only logs a warning.

## Environments

The `env` key of your `.env` (or `environment`, read when `env` is missing) names the environment.

```ini
env=debug
```

| Value | `Cube\isProduction()` | `Cube\isDebug()` | An uncaught exception answers |
|---|---|---|---|
| starts with `prod` | `true` | `false` | `Internal Server Error` |
| `debug`, `dev`, `development`, `local`, `test`, `testing` | `false` | `true` | the message and the trace |
| anything else (`staging`, `preprod`...) | `false` | `false` | `Internal Server Error` |
| no key | `false` | `false` | `Internal Server Error` |

The comparison ignores case. `Cube\environmentName()` gives the raw value.

Two defaults follow the environment, both set by `AutoloaderConfiguration` :

- `isProduction()` turns on the cache of class discovery, which must then be emptied after each
  deployment : see [Deployment](./34-deployment.md).
- `isDebug()` turns on `display_errors` : outside a debug environment, PHP warnings and notices go to
  the error log instead of being printed into the response, absolute paths included.

## The application key

`APP_KEY` signs the Redis cache and queue payloads and keys `Cube\encrypt()`. It must hold at least
32 bytes : a missing or shorter key throws a `RuntimeException` the first time it is needed.
`php do key:generate` prints a random one, ready to paste

```sh
php do key:generate
# APP_KEY=...
```

See [Encryption and signing](./26-encryption-and-signing.md).

## Running the application

For development, `web:serve` starts PHP's built-in server on `Public/`

```sh
php do web:serve        # http://localhost:8000
php do web:serve 8080   # another port
```

To run the docker stack, create your `.env` **before** the first `docker compose up` :
`docker-compose.yml` mounts it into the php-fpm container (it is kept out of the image, which would
carry your secrets in its layers), and Docker creates a directory in place of a missing file.

```sh
make build   # docker compose build
make up      # docker compose up -d, nginx answers on http://localhost:8081
make down
```

The compose file mounts `App/` (so your changes apply without a rebuild) and `Storage/` (logs, cache
and files survive the container). Mount any other application directory you declare in `cube.php`
the same way.

Commands run from the root of your project, the directory holding `vendor/` : Cube looks for
`vendor/yonis-savary/cube` from the current directory upwards and works from there.

`Storage/` holds your logs, cache, sessions files and the cached configuration : keep it outside your
web root (the provided nginx serves `Public/` only) and readable by your application only.

Deployment and production caching are covered in [Deployment](./34-deployment.md).

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./01-introduction.md">Previous : Introduction</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./03-configuration.md">Next : Configuration</a></div></td></tr></table>
