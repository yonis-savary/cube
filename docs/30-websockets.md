<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./29-queues.md">Previous : Queues</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./31-unix-socket-server.md">Next : Unix socket server</a></div></td></tr></table>

# Websockets

Cube pushes server-side events to browsers through **channels** : your backend emits on a channel,
and every browser subscribed to it receives the payload. PHP cannot hold a socket open while it
answers requests, so a separate long-running process, started with `php do websocket:serve`, holds
the connections.

Here is a channel, the 80% case

```php
class JobChannel extends Channel
{
    public function getRoute(): string
    {
        return '/job/{id}';
    }

    public function authorize(array $slugs, RequestInterface $request): ?string
    {
        return null;   // anyone may subscribe
    }
}
```

Your backend emits on it

```php
$jobChannel->emit(['status' => 'ended'], [$job->id]);
```

And the browser listens

```js
const socket = new WebSocket('ws://localhost:8088/job/12');
socket.onmessage = (message) => console.log(JSON.parse(message.data));   // {status: "ended"}
```

## How it fits together

The work is split in three : your **backend** stays a normal request-response application, a
long-running **HTTP + Socket** process holds the connections, and your **frontend** subscribes to it.

```
 _________           ________________________           __________
| Backend |-------->| (HTTP + Socket) Server |<------->| Frontend |
|_________|         |________________________|         |__________|
```

A signal travels like this

0. the frontend subscribes to a channel path on the socket side of the server
1. your **backend** posts a signal to the server's HTTP side
2. the server routes it to the matching **channel**
3. the channel forwards it to every frontend subscribed to that exact path

The backend never talks to the browser directly — it talks to the socket process, which is the only
long-lived thing in the picture.

## Configuration

Two [configuration elements](./03-configuration.md), because two processes are involved.
`WebsocketConfiguration` is read by the socket process — it says what to bind.
`BroadcastConfiguration` is read by your backend — it says where to reach it.

```php
new WebsocketConfiguration(
    // where the frontend connects
    websocketHost: '0.0.0.0',
    websocketPort: 8088,
    // where the backend connects — defaults to 127.0.0.1, open it only to your backend
    httpHost: '0.0.0.0',
    httpPort: 8089,
    // shared by the socket process and Broadcast, which sends it with every signal
    broadcastSecret: env('WEBSOCKET_SECRET'),
    // origins a browser may connect from, null re-uses CORSConfiguration's allowedOrigins
    allowedOrigins: ['https://shop.example.org'],
),

new BroadcastConfiguration(
    // how the backend reaches the socket process — with containers, the service name
    httpHost: 'websocket',
    // null re-uses WebsocketConfiguration's httpPort
    httpPort: null,
    // the address browsers connect to, used to build the URLs you hand them
    socketHost: 'wss://events.shop.example.org',
    socketPort: 443,
),
```

Every `BroadcastConfiguration` value falls back to its `WebsocketConfiguration` counterpart when it
is `null` : `httpHost`, `httpPort`, `socketHost` (`websocketHost`), `socketPort` (`websocketPort`) and
`broadcastSecret`. A host without a scheme gets `http://` for the HTTP side and `ws://` for the socket
side. Set `socketHost` and `socketPort` whenever the address a browser sees differs from the one the
process binds — the normal case behind a reverse proxy, and the default one too, since
`websocketHost` is `0.0.0.0`.

### Securing the HTTP side

The HTTP side is what makes a signal appear on every browser.

- With a `broadcastSecret`, it answers `403` to any request that does not carry it in the
  `X-Broadcast-Secret` header — `Broadcast` adds it for you.
- Without one, anyone who can reach it can emit on any channel : the server logs a warning when it
  listens on the loopback (`127.0.0.1`, `localhost`, `::1`), and refuses to start on any other
  `httpHost`.
- It only accepts a `POST` with an `application/json` body (`400` otherwise) and sends no CORS
  headers, so a web page cannot use a visitor's browser to emit.

Either way, expose only the websocket port publicly.

### Origins

A browser attaches the visitor's cookies to the handshake whatever page opens it, so the socket
process first checks the `Origin` header against `allowedOrigins` (`['*']` accepts any). A browser
connecting from another origin is closed before reaching the channel. A client sending no `Origin`,
which a browser never does, is not concerned.

When `allowedOrigins` is `null` and `CORSConfiguration` allows no origin either, **every browser is
refused** : set one of them.

### Memory

Ratchet caps a client message at a quarter of `memory_limit`, so keep one set for the websocket
process : with `-1`, a single client can exhaust its memory, and the server logs a warning when it
starts.

## Creating a channel

A channel is a class extending `Channel`, with a route. The route's slugs are what separate one
stream from another : a subscriber to `/job/12` only receives what is emitted on `/job/12`.

Channels are discovered by class, so the file is enough — nothing to register. The socket process
builds every channel when it starts.

A connection to a path no channel answers is closed. Messages a client sends on the socket are
ignored : channels only flow from the backend to the browser.

## Deciding who may subscribe

Every channel implements `authorize()` to accept or refuse a subscription. It runs before the
connection joins, and receives the slug values of the path being subscribed to, plus the handshake
request (a PSR-7 `RequestInterface`) carrying the client's headers, cookies and query string. Return
`null` to allow, or a string to refuse

```php
class JobChannel extends Channel
{
    public function getRoute(): string
    {
        return '/job/{id}';
    }

    public function authorize(array $slugs, RequestInterface $request): ?string
    {
        // ws://host:8088/job/12?token=... : browsers cannot set headers on a websocket
        parse_str($request->getUri()->getQuery(), $query);

        if (!$job = Job::find($slugs['id']))
            return 'Unknown job';

        if (!hash_equals($job->subscription_token, (string) ($query['token'] ?? '')))
            return 'Invalid token';

        return null;
    }
}
```

A refused client receives `{"error": "Invalid token"}` on the socket, then the connection is closed.

The socket process is long-running and serves every client : it has no PHP session of its own, so
`Authentication` cannot tell who subscribes. Authenticate from the request — a token your backend
issued, or the session cookie read from the `Cookie` header and checked against your session storage.

`authorize()` is abstract, so a channel cannot be public by mistake : a channel anyone may follow
says so by returning `null`.

## Emitting from your backend

Ask for the channel as a dependency and call `emit()` with the payload and the route parameters,
in the order of the route's slugs. A model given as a parameter is replaced by its id.

```php
class JobRunner
{
    public function __construct(
        protected JobChannel $jobChannel
    ) {}

    public function process(Job $job): void
    {
        // ...
        $this->jobChannel->emit(
            ['status' => 'ended', 'exitCode' => $exitCode],
            [$job]
        );
    }
}
```

`emit()` sends the signal asynchronously and returns a `bool` : whether the connection to the socket
process opened. It does not wait for the answer, so it is neither a delivery receipt nor a sign the
server accepted the signal — a wrong secret or a channel with no subscriber both look like `true`.
The exchange is logged to `Storage/Logs/broadcast-client.csv`.

When you send many signals to the same path, lock the parameters once instead of rebuilding the path
each time

```php
$this->jobChannel->lockParams([$job->id]);

$this->jobChannel->emit(['status' => 'started']);
$this->jobChannel->emit(['progress' => 50]);
$this->jobChannel->emit(['status' => 'ended']);

$this->jobChannel->unlockParams();
```

While locked, the parameters passed to `emit()` are ignored — that is the point, and also the trap :
unlock before emitting on another path.

Every subscriber of the path receives the payload as JSON, exactly as you emitted it.

## Pointing the frontend at a channel

`path()` builds the channel path from route parameters, and `BroadcastConfiguration` knows the
public websocket origin. Hand the full URL to your frontend

```php
class JobController extends Controller
{
    public static function subscription(Request $request, int $id, JobChannel $jobChannel): Response
    {
        $origin = BroadcastConfiguration::resolve()->getWebsocketOrigin();   // ws://localhost:8088

        return Response::json(['url' => $origin.$jobChannel->path([$id])]);
    }
}
```

`redirect()` builds the same URL and answers a `307` whose `Location` points to it, for clients
that read the header themselves

```php
return $jobChannel->redirect([$id]);   // Location: ws://localhost:8088/job/394898
```

## Running the server

```sh
php do websocket:serve
```

It is a long-running process : give it a supervisor, one service, and keep its HTTP port off the
public network

```yaml
services:
  websocket:
    image: my-app
    command: ["php", "do", "websocket:serve"]
    ports:
      - "8088:8088"   # frontend
    expose:
      - "8089"        # backend only
    restart: always
```

The server logs connections, refusals and dispatches through the `Logger` component, so to
`Storage/Logs/cube.csv` by default. The command accepts `-l` / `--log`, meant to echo that log to
stdout, but it currently has no effect : the log still goes to `cube.csv`.

The process builds your channels once, at start : restart it after a deployment.

## Reference

| Command | Does |
|---|---|
| `php do websocket:serve` | starts the websocket and HTTP servers |

`WebsocketConfiguration`, read by the socket process

| Option | Default | Does |
|---|---|---|
| `websocketHost` | `'0.0.0.0'` | address the websocket server binds |
| `websocketPort` | `8088` | port the websocket server binds |
| `httpHost` | `'127.0.0.1'` | address the HTTP server, which receives the signals, binds |
| `httpPort` | `8089` | port the HTTP server binds |
| `broadcastSecret` | `null` | secret required in the `X-Broadcast-Secret` header — required when `httpHost` is not a loopback |
| `allowedOrigins` | `null` | origins a browser may connect from, `['*']` for any, `CORSConfiguration`'s when `null` |

`BroadcastConfiguration`, read by your backend — every option falls back to `WebsocketConfiguration`

| Option | Falls back to | Used for |
|---|---|---|
| `httpHost` | `httpHost` | reaching the HTTP server from the backend |
| `httpPort` | `httpPort` | same |
| `socketHost` | `websocketHost` | the URL given to browsers (`getWebsocketOrigin()`, `redirect()`) |
| `socketPort` | `websocketPort` | same |
| `broadcastSecret` | `broadcastSecret` | the `X-Broadcast-Secret` header sent with every signal |

| `Channel` method | Does |
|---|---|
| `getRoute(): string` | the route subscribers connect to — required |
| `authorize(array $slugs, RequestInterface $request): ?string` | `null` accepts the subscription, a string refuses it — required |
| `emit(array $data, array $routeParams = []): bool` | sends `$data` to the subscribers of that path |
| `path(array $routeParams = []): string` | the channel path for those parameters |
| `lockParams(array $params)` / `unlockParams()` | fixes the path used by `emit()` |
| `redirect(array $routeParams = []): Response` | a `307` to the full websocket URL |

| HTTP side answers | When |
|---|---|
| `200` | the signal was dispatched |
| `400` | not a `POST`, not JSON, or the internal `__class` key is missing |
| `403` | the broadcast secret is missing or wrong |
| `422` | the `__class` key names no known channel |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./29-queues.md">Previous : Queues</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./31-unix-socket-server.md">Next : Unix socket server</a></div></td></tr></table>
