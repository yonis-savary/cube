<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./33-testing.md">Previous : Testing</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./README.md">Next : Readme</a></div></td></tr></table>

# Deployment

In production, Cube caches what it discovers about your project, hides error details from clients and
logs to `Storage/Logs`. This page covers what to set, what to run after each release, and what the
provided Docker stack does for you.

Here is a release, once your server is set up

```sh
git pull
composer install --no-dev --optimize-autoloader
php do migrate                                  # apply new migrations
php do cube:dispatch --event PostDeployment     # empty Cube's caches, the configuration cache included
php do configuration:cache                      # only if you cache your configuration
# then restart php-fpm, to drop its APCu copy of the discovery cache
```

## The production environment

Name the environment in your `.env` (see [Installation](./02-installation.md#environments))

```ini
env=prod
APP_KEY=...
```

Any value starting with `prod` makes `Cube\isProduction()` true. Here is what follows from it

| Behaviour | In production | Controlled by |
|---|---|---|
| class discovery and application files are cached | yes | `AutoloaderConfiguration(cached:)`, default `isProduction()` |
| PHP warnings printed into the response | no | `AutoloaderConfiguration(displayErrors:)`, default `isDebug()` |
| an uncaught exception shows its message and trace | no | `isDebug()` |
| `php do migrate` regenerates your models | no : you deploy the models you generated and committed | `isProduction()` |

`isDebug()` is only true for `debug`, `dev`, `development`, `local`, `test` and `testing` : a
`staging` or `preprod` environment hides errors too, but does not cache discovery unless you say so.
Set both options explicitly to decouple them from the environment name

```php
// cube.php
new AutoloaderConfiguration(cached: true, displayErrors: false),
```

`APP_KEY` must hold at least 32 bytes : see [Encryption and signing](./26-encryption-and-signing.md).

## The discovery cache

With `AutoloaderConfiguration(cached: true)`, Cube stops scanning your project on each request. It
keeps

- the list of your applications and of their `Routes/`, `Assets/`, `Requires/`... and `Views/` files,
- the class index : every answer to "which classes extend, implement or use this", the one controllers,
  commands, migrations and factories are found with.

Both live in the `Cache` component (`Storage/Cache` by default, see
[Storage and caching](./21-storage-and-caching.md)). When APCu is enabled, Cube also snapshots them in
APCu, and a php-fpm worker finding the snapshot boots from memory without exploring your
directories.

This cache never notices a change by itself : a new controller, a new route file, a new command stay
invisible until it is emptied. Empty it after each deployment

```sh
php do cube:dispatch --event PostDeployment
```

then restart php-fpm. The command runs in the CLI, which cannot reach the APCu memory of php-fpm :
only a restart drops that copy.

| Cache state | A new class or file in a known application | A newly declared application |
|---|---|---|
| on disk only | ignored until emptied | loaded |
| APCu snapshot loaded | ignored until php-fpm restarts | ignored until php-fpm restarts |

Keep in mind

- `PostDeployment` empties the discovery entries and the [remembered routes](#caching-routes) only :
  what your application cached stays. Run `php do cache:clear` to empty everything.
- Restart php-fpm **after** the dispatch : a request served in between reads the old disk cache and
  puts it back into APCu.
- APCu is shared by every pool of one php-fpm master, and restarting the master restarts all of them :
  give each application its own php-fpm master (the provided Docker stack does).

## Caching the configuration

`configuration:cache` stores your configuration elements, so `cube.php` and its imports are no longer
included on each request (see [Configuration](./03-configuration.md#caching-the-configuration))

```sh
php do configuration:cache
```

Once cached, **`cube.php` is not read anymore**. A change to `cube.php`, to an imported file or to a
`.env` value they read only applies after you run `configuration:cache` again, or once
`PostDeployment` dropped the cache. `cache:clear` does not touch it : it lives apart, under
`Storage/cube/configuration`.

Run `configuration:cache` **after** dispatching `PostDeployment` on each release : the dispatch drops
the previous release's configuration, and Cube reads `cube.php` again until you cache it anew.

## Caching routes

`RouterConfiguration(cached: true)` remembers, in the `Cache` component, which route answers each
method and path, so a remembered request skips loading your controllers and route files

```php
new RouterConfiguration(cached: true),
```

Only routes without slugs whose callback is a `[Controller::class, 'method']` array are remembered,
and the `WebAPI` services you configure are still asked before a remembered route runs (see
[Routing and middlewares](./11-routing-and-middlewares.md)). The remembered routes are emptied by
`PostDeployment` and by `php do cache:clear`.

## Errors

Cube installs its error and exception handlers when it boots.

| What happens | Where it goes | What the client gets |
|---|---|---|
| an uncaught exception | `Storage/Logs/fatal.csv`, with its trace | `500 Internal Server Error`, as plain text ; the message and trace are added only when `isDebug()` |
| an uncaught exception in a command | `Storage/Logs/fatal.csv` | the console prints the exception class, message, file and line |
| logging itself fails while handling it | nowhere | a bare `500` with `Internal Server Error` |
| a PHP deprecation | `Storage/Logs/warnings.csv` | nothing |
| another PHP warning or notice | PHP's error log | printed only when `displayErrors` is on |

## Logs

Every web request writes two lines to `Storage/Logs/cube.csv` (the method and path, then the status and
content type of the response) and one summary line to the standard output, which `docker compose logs`
shows. The other files Cube writes are listed in [Logging](./09-logging.md#files-cube-writes).

Cube does not rotate its log files : have `logrotate`, or your platform, take care of `Storage/Logs`.

## Protecting `Storage`

`Storage` holds what must never be served

| Content | Why it is sensitive |
|---|---|
| `cube/configuration` | your cached configuration elements, passwords included |
| `Logs/` | traces of your errors, paths, request details |
| `Cache/` | whatever your application cached |
| your files and an SQLite database | your data |

Keep it outside your web root and readable by your application only. The provided nginx serves
`Public/` only, and `Storage` stays out of git and out of the Docker image. A `RedisSession` also
puts its password in `session.save_path`, which `phpinfo()` shows : never expose `phpinfo()` in
production.

## Background processes

Long-running and scheduled work does not go through php-fpm

- add the line printed by `php do routine:generate` to your crontab : it runs `php do routine:launch`
  every minute, which launches your [schedules](./28-schedules.md) and [queues](./29-queues.md) ;
- supervise the long-running servers, `php do websocket:serve` ([Websockets](./30-websockets.md)) and
  `php do unix:serve` ([Unix socket server](./31-unix-socket-server.md)), and restart them on each
  release : they keep running the code they started with.

## The Docker stack

The server files include a production-ready stack (see [Installation](./02-installation.md#running-the-application))

| File | Does |
|---|---|
| `Docker/Dockerfile.php` | `php:8.5-fpm` with APCu (also enabled for the CLI), PHP's production `php.ini`, `composer install --no-dev --optimize-autoloader`, your project copied to `/var/www`, `Storage` owned by `www-data` |
| `Docker/vhost.conf` | nginx serving `/var/www/Public`, every unknown path sent to `index.php` |
| `docker-compose.yml` | the `php-fpm` and `nginx` services, nginx on port `8081` ; mounts `Storage/` (persistent) and `.env` (read-only, kept out of the image) |
| `Makefile` | `build`, `up`, `down` and `deploy` |

`make deploy` runs, in order

```sh
git pull
docker compose build
docker compose down
docker compose run --rm --user www-data php-fpm php do migrate:migrate
docker compose run --rm --user www-data php-fpm php do cube:dispatch --event PostDeployment
docker compose run --rm --user www-data php-fpm php do configuration:cache
docker compose up -d
```

The image is built while the previous release still answers. The release tasks then run on the new
image while no php-fpm serves requests, so the new php-fpm starts with an empty APCu and fresh disk
caches : no request can put the old discovery cache back. Remove the `configuration:cache` line if
you do not cache your configuration.

The stack runs the image as built : your applications are copied into it, not mounted. To edit
`App/` without rebuilding in development, add `- ./App:/var/www/App` to the `php-fpm` volumes of
your local copy.

## Checklist

- [ ] `env=prod` in the `.env`, and an `APP_KEY` of at least 32 bytes (`php do key:generate`)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] the web server serves `Public/` only ; `Storage` is outside the web root, readable by the
      application only
- [ ] your models were generated and committed before the release (`migrate` does not write them in
      production)
- [ ] `php do migrate`
- [ ] `php do cube:dispatch --event PostDeployment`, then `php do configuration:cache` if you cache
      the configuration, **then** restart php-fpm
- [ ] the long-running servers restarted, the `routine:launch` cron line in place
- [ ] log rotation set up for `Storage/Logs`
- [ ] security headers declared in `RouterConfiguration(securityHeaders:)` (Cube only adds
      `X-Content-Type-Options: nosniff` by itself), CORS origins set (see
      [Routing and middlewares](./11-routing-and-middlewares.md))
- [ ] each application on its own php-fpm master

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./33-testing.md">Previous : Testing</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./README.md">Next : Readme</a></div></td></tr></table>
