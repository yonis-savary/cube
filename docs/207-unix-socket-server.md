<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./206-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./999-large-upload.md">Next : Large upload</a></div></td></tr></table>

# Unix socket server

`php do web:serve --socket=<path>` serves an HTTP API on a unix socket instead of a TCP port, for APIs
that are internal to a system : only processes of the same machine that can open the socket file
reach it. `UnixSocketServer` runs it in a single long-lived process and starts each request from a
clean set of components.

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
php do web:serve --socket=/run/my-app.sock
curl --unix-socket /run/my-app.sock http://localhost/internal/ping
```

## Declaring routes

The socket server starts from an **empty router** : it does not load your controllers, your
`Routes/` files nor the configured `WebAPI`s. Before listening, it dispatches `SocketServerSetup`
with that router, and its listeners declare everything it serves.

```php
SocketServerSetup::on(function (SocketServerSetup $event) {
    $event->router->addRoutes(
        Route::get('/health', fn () => Response::ok('up'))
    );

    // Loads the routes of a file into a group
    $event->router->group('/internal', [InternalTokenMiddleware::class], requires: [
        'App/Socket/routes.php',
        'App/Socket/stock-routes.php',
    ]);
});
```

`Router::require()` loads a routes file into the group being declared, and `group()` takes a
`requires` list doing the same. Paths are relative to your project root, and a missing file throws an
`InvalidArgumentException`.

Keep these files **out of** `Routes/` and `Router/` : Cube loads those directories into the HTTP
router of your application, so the routes would be served over HTTP too.

## Calling the socket from PHP

`HttpClient` connects through curl, which only needs the socket path. Use any host in the URL, the
socket decides where the request goes.

```php
$response = (new Request('GET', 'http://localhost/internal/ping'))->fetch(
    curlMutator: fn (\CurlHandle $handle) => curl_setopt($handle, CURLOPT_UNIX_SOCKET_PATH, '/run/my-app.sock')
);
```

## What survives between requests

Each request runs in a `RequestContext` : every component created during the request is removed
when it ends, so nothing leaks from one request into the next. A few components are kept for the
whole life of the process

| Component | Why it is kept |
|---|---|
| `Router` | the routes declared at setup |
| `Events` | the listeners your `Requires/` files registered at boot |
| `Configuration` | your `cube.php` |

Everything else — `Database`, `Session`, `Authentication`, your own components — is built again for
each request. The database connection, for example, is opened by the first query of each request.

Listeners added to `Events` **during** a request are kept too, so they pile up request after
request : register them at boot.

Static properties are not components, `RequestContext` does not touch them : whatever a request
stores in one is still there for the next.

## Differences with an HTTP request

The process never ends between two requests, so a few things behave differently

- `exit`, `die` or `Response::exit()` stop the whole server, not only the request.
- Cookies and sessions are not supported : `setcookie()` (used by `RememberMe`) and the session
  drivers rely on the PHP web server, which is not involved here.
- Uploaded files are written to temporary files, then removed once the response is sent unless you
  moved them with `Upload::move()`.
- Requests have no IP address : `$request->getIp()` returns `null`.
- Requests are handled one at a time. A slow request makes the others wait.

## Errors

An exception escaping your callback does not stop the server. It is logged to `socket-fatal.csv` and
answered with a `500`, with the message and trace in a debug environment (`Response::fromThrowable()`, the
same response as the HTTP entry point).

## Running it

- When the socket file already exists, the server tries to connect to it : if nothing answers, the
  file is a leftover of a killed server and gets removed, otherwise the command refuses to start.
- On `SIGINT` (`Ctrl-C`) and `SIGTERM`, the server closes the socket and removes its file. Handling
  those signals needs the `pcntl` extension.
- The socket file gets the permissions of the process `umask` : that is what decides who can call the
  API.
- A unix socket path is limited to 108 characters by the system.
- Run it under a supervisor (systemd, supervisord, a container restart policy) to restart it after a
  crash.

## Reference

| Command | Description |
|---|---|
| `php do web:serve --socket=<path>` | Serve the socket routes on `<path>` |
| `php do web:serve [port]` | Unchanged : the PHP built-in server on `localhost:<port>` (8000 by default) |

| Event | Fired when | Carries |
|---|---|---|
| `SocketServerSetup` | before the socket server starts listening | `router`, the empty router to fill |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./206-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./999-large-upload.md">Next : Large upload</a></div></td></tr></table>
