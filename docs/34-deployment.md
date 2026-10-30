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

- add a line printed by `php do routine:generate` to your crontab : it runs `php do routine:launch`
  every minute, which launches your [schedules](./28-schedules.md) (not your [queues](./29-queues.md)) ;
- supervise the long-running processes, `php do cube:queue` ([Queues](./29-queues.md)),
  `php do websocket:serve` ([Websockets](./30-websockets.md)) and `php do unix:serve`
  ([Unix socket server](./31-unix-socket-server.md)), and restart them on each release : they keep
  running the code they started with. With the Docker stack, each one is a service (see
  [Background services](#background-services)).

## The Docker stack

The server files include two stacks built from the same `Docker/Dockerfile.php` (see
[Installation](./02-installation.md#running-the-application))

| | Development | Production |
|---|---|---|
| started with | `make dev` (a plain `docker compose up`) | `make build`, `make up`, `make deploy` |
| compose files | `docker-compose.yml` + `docker-compose.override.yml` | `docker-compose.yml` only |
| image target | `dev` | `prod` |
| your project | mounted on `/var/www` : changes apply at once | copied into the image : rebuild to change it |
| `vendor/` | in the `vendor` volume, from the image, dev dependencies included | in the image, `--no-dev --classmap-authoritative` |
| `php.ini` | `php.ini-development` | `php.ini-production` |
| OPcache | checks your files on each request | `validate_timestamps=0` : never looks at the files again |

Both run php-fpm with APCu (also enabled for the CLI) and nginx serving `/var/www/Public` on port
`8081`, and both mount `Storage/` (persistent) and `.env` (read-only, kept out of the image).

Every process of the stack runs as `www-data`, never as root, and the image gives `www-data` the
user and group ids of the host user owning `Storage/` and `.env`. A file written in `Storage/` by a
container is yours on the host, and a file you create there is writable by the containers. See
[Container user](#container-user).

```sh
docker compose exec php-fpm php do cache:clear
make exec   # a bash in php-fpm
```

Docker reads `docker-compose.override.yml` on its own whenever it is present : a plain
`docker compose up` starts the **development** stack. The Makefile production targets pass
`-f docker-compose.yml` so that never happens on a server, so go through them.

The classmap is authoritative in production : a class composer did not see when building the image
does not exist. Every application must be autoloaded by `composer.json` (see
[Applications and discovery](./04-applications-and-discovery.md)).

In development, the `vendor` volume is filled from the image the first time only. After changing your
`composer.json`, install inside the container, or drop the volume to rebuild it

```sh
docker compose run --rm php-fpm composer install
# or
docker compose down -v && make dev
```

Xdebug is ready to enable in development : uncomment its lines in the `dev` stage of
`Docker/Dockerfile.php` and the `extra_hosts` of `docker-compose.override.yml`, then `make dev`.

`make deploy` runs, in order

```sh
git pull
docker compose -f docker-compose.yml build
docker compose -f docker-compose.yml down
docker compose -f docker-compose.yml run --rm php-fpm php do migrate:migrate
docker compose -f docker-compose.yml run --rm php-fpm php do cube:dispatch --event PostDeployment
docker compose -f docker-compose.yml run --rm php-fpm php do configuration:cache
docker compose -f docker-compose.yml up -d
```

The image is built while the previous release still answers. The release tasks then run on the new
image while no php-fpm serves requests, so the new php-fpm starts with an empty APCu and fresh disk
caches : no request can put the old discovery cache back. Remove the `configuration:cache` line if
you do not cache your configuration.

nginx reads your code through a volume shared with php-fpm : always `down` before `up` when you
change the image, as `make deploy` does. An `up -d` alone keeps the previous content of that volume,
and nginx keeps serving the previous `Public/`.

## Background services

With the Docker stack, each long-running process is one more service on the image php-fpm builds,
`${COMPOSE_PROJECT_NAME}-php`. Compose names the project after its directory, unless your `.env`
sets `COMPOSE_PROJECT_NAME` (see `.env.example`) : the prefix keeps two projects on the same
machine from sharing one image.

Here is a queue worker, in `docker-compose.yml`

```yaml
  invoice-mailer:
    image: ${COMPOSE_PROJECT_NAME}-php
    command: ["php", "do", "cube:queue", "--queue=App\\Queue\\InvoiceMailerQueue", "-l"]
    restart: unless-stopped
    volumes:
      - ./Storage:/var/www/Storage
      - ./.env:/var/www/.env:ro
    depends_on:
      - php-fpm # builds the image
```

And in `docker-compose.override.yml`, so that it runs your mounted code in development

```yaml
  invoice-mailer:
    volumes:
      - .:/var/www
      - vendor:/var/www/vendor
```

| Process | Command | Notes |
|---|---|---|
| a queue worker | `cube:queue --queue=<class> -l` | one service per queue |
| the websocket server | `websocket:serve -l` | `ports:` the frontend port, `expose:` the backend one, see [Websockets](./30-websockets.md#running-the-server) |
| the unix socket server | `unix:serve <path>` | put the socket in a volume shared with php-fpm |

- `-l` sends the process log to stdout, read by `docker compose logs`.
- Other services reach a process by its service name : `BroadcastConfiguration(httpHost: 'websocket')`.
- The image includes `pcntl` : on `docker compose stop`, a worker finishes its current item before
  leaving.
- `make deploy` restarts every service on the new image. In development, run
  `docker compose restart <service>` after changing the code it runs.
- Schedules are not a service : they stay in the host's crontab (`php do routine:generate`).

## Container user

A container writing in a directory mounted from the host writes with its own user id : files
created as root, or as the `www-data` of the image (uid 33), cannot be modified by your user on the
host, and files you create there cannot be written by the container. The Docker stack avoids it by
building the image for your ids, given in `.env`

```sh
UID=1000   # id -u
GID=1000   # id -g
```

`Docker/Dockerfile.php` gives them to `www-data` and runs every process as that user : php-fpm,
`make exec`, the commands of `make deploy` and your [background services](#background-services).
Without them, the ids default to `1000`, the first user of most Linux systems.

The ids are written into the image when it is built : rebuild it after changing them (`make dev`,
or `make build` then `make up`).

The Makefile creates `Storage/` before starting the stack : a mounted directory missing on the host
would be created by Docker, as root, and the containers could not write in it.

### Limits

- **An image built elsewhere.** An image pushed to a registry carries the ids of the machine that
  built it. Choose the target ids beforehand, the ones of the user owning the project on your
  servers, and build with them : `UID=1001 GID=1001 make build`, or set them in your CI. Every
  server running that image must have its `Storage/` and `.env` owned by those ids.
- **Several users on one server.** The image has one owner : every person deploying the project
  runs it with the same ids, those of the user owning the project directory.
- **Docker Desktop (macOS, Windows).** Its file sharing already hides the ownership of mounted
  files, so the ids change nothing there. They do no harm either.
- **Upgrading from a stack running as uid 33.** Your `Storage/` belongs to `www-data` of the
  former image : give it back to your user once, `sudo chown -R $(id -u):$(id -g) Storage`.

## Checklist

- [ ] `env=prod` in the `.env`, and an `APP_KEY` of at least 32 bytes (`php do key:generate`)
- [ ] with Docker, `UID` and `GID` in the `.env` match the owner of `Storage/` and `.env`, or the
      target ids chosen for an image built elsewhere (see [Container user](#container-user))
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
