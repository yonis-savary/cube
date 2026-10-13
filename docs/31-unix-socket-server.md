<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./30-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./32-static-server.md">Next : Static server</a></div></td></tr></table>

# Unix socket server

`php do unix:serve <path>` serves an HTTP API on a unix socket instead of a TCP port, for APIs that
are internal to a system : only processes of the same machine that can open the socket file reach
it. `UnixSocketServer` runs it in a single long-lived process and starts each request from a clean
set of components.

Here is an example, in two files of your application

```php
// App/Requires/socket-server.php — included at boot, registers the routes of the socket
SocketServerSetup::on(
    fn (SocketServerSetup $event) => $event->router->group('/internal', requires: ['App/Socket/routes.php'])
);
```

```php
// App/Socket/routes.php — `$router` is the socket's router
$router->addRoutes(
    Route::get('/ping', [PingController::class, 'ping']),
    Route::post('/products/{product}/stock', [StockController::class, 'update']),
);
```

Then start the server and call it

```sh
php do unix:serve /run/shop.sock
curl --unix-socket /run/shop.sock http://localhost/internal/ping
```

## Declaring routes

The socket server starts from an **empty router** : it does not load your controllers, your
`Routes/` files nor the configured `WebAPI`s. Before listening, it dispatches the
[event](./08-events.md) `SocketServerSetup` with that router, and its listeners declare everything it
serves

```php
SocketServerSetup::on(function (SocketServerSetup $event) {
    $event->router->addRoutes(
        Route::get('/health', fn () => Response::ok('up'))
    );

    // Loads the routes of files into a group
    $event->router->group('/internal', [InternalTokenMiddleware::class], requires: [
        'App/Socket/routes.php',
        'App/Socket/stock-routes.php',
    ]);
});
```

`Router::require()` loads a routes file into the group being declared, and `group()` takes a
`requires` list doing the same (see [Routing and middlewares](./11-routing-and-middlewares.md#loading-a-routes-file-into-a-group)).
Paths are relative to your project root, and a missing file throws an `InvalidArgumentException`.

Keep these files **out of** `Routes/` and `Router/` : Cube loads those directories into the HTTP
router of your application, so the routes would be served over HTTP too.

Register the listener from a file included at boot (`Requires/`, see
[Applications and discovery](./04-applications-and-discovery.md#directory-structure)), so it exists
before the server dispatches the event.

## Calling the socket from PHP

Write a [connector](./27-http-client.md#a-connector) whose `baseUnixSocket()` returns the socket
path : every request it sends goes through the socket, `fetch()` and `fetchAsync()` alike

```php
class InternalApiConnector extends HttpClient
{
    public function baseUnixSocket(): ?string
    {
        return '/run/shop.sock';
    }

    public function ping(): Response
    {
        return $this->get('/internal/ping');
    }
}
```

The path needs no host : without a `baseURL()`, Cube sends it to `http://localhost`, since the
socket decides where the request goes anyway. A redirection is followed through the same socket when
it stays on that host, and over the network otherwise.

## What survives between requests

Each request runs in a [`RequestContext`](./06-components.md#running-code-with-fresh-components) :
every component created during the request is removed when it ends, so nothing leaks from one
request into the next. A few components are kept for the whole life of the process

| Component | Why it is kept |
|---|---|
| `Router` | the routes declared at setup |
| `Logger` | the server logs to `socket.csv` |
| `Events` | the listeners your `Requires/` files registered at boot |
| `Configuration` | your `cube.php` |

Everything else — `Database`, `Session`, `Authentication`, your own components — is built again for
each request. The database connection, for example, is opened by the first query of each request.

Listeners added to `Events` **during** a request are kept too, so they pile up request after
request : register them at boot.

Static properties are not components, `RequestContext` does not touch them : whatever a request
stores in one is still there for the next.

To keep other components, build the server yourself : the third constructor argument lists the
persistent components, the `Router` being always kept

```php
(new UnixSocketServer('/run/shop.sock', persistentComponents: [
    Events::class, Configuration::class, Logger::class, Database::class,
]))->serve();
```

## Differences with an HTTP request

The process never ends between two requests, so a few things behave differently

- `exit`, `die` or `Response::exit()` stop the whole server, not only the request.
- Cookies sent by the client are readable, but nothing can set one : `setcookie()` (used by
  `RememberMe`) and the session drivers rely on the PHP web server, which is not involved here.
- Uploaded files are written to temporary files, then removed once the response is sent unless you
  moved them with `Upload::move()`.
- Requests have no IP address : `$request->getIp()` returns `null`.
- Requests are handled one at a time. A slow request makes the others wait.

## Errors

An exception escaping your callback does not stop the server. It is logged to `socket-fatal.csv` and
answered with a `500`, with the message and trace in a debug environment (`Response::fromThrowable()`,
the same response as the HTTP entry point).

Each request and its response are logged as on the HTTP side, in `Storage/Logs/socket.csv`.

## Running it

- When the socket file already exists, the server tries to connect to it : if nothing answers, the
  file is a leftover of a killed server and gets removed, otherwise the command refuses to start.
- On `SIGINT` (`Ctrl-C`) and `SIGTERM`, the server closes the socket and removes its file. Handling
  those signals needs the `pcntl` extension : with PHP's default event loop, the server does not
  start without it.
- The socket file gets the permissions of the process `umask` : that is what decides who can call the
  API.
- A unix socket path is limited to 108 characters by the system.
- Run it under a supervisor (systemd, supervisord, a container restart policy) to restart it after a
  crash, and restart it after a deployment : it holds your code in memory.

## Reference

| Command | Does |
|---|---|
| `php do unix:serve <path>` | serves the socket routes on `<path>` |

| Event | Fired when | Carries |
|---|---|---|
| `SocketServerSetup` | before the socket server starts listening | `router`, the empty router to fill |

| `UnixSocketServer` constructor argument | Default | Does |
|---|---|---|
| `$socketPath` | required | the socket file to create |
| `$router` | an empty `Router` | the router to serve, filled by `SocketServerSetup` listeners |
| `$persistentComponents` | `[Events::class, Configuration::class, Logger::class]` | components kept between requests, along with the `Router` |

| Log file | Holds |
|---|---|
| `Storage/Logs/socket.csv` | the server log, requests and responses |
| `Storage/Logs/socket-fatal.csv` | exceptions escaping a callback |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./30-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./32-static-server.md">Next : Static server</a></div></td></tr></table>
