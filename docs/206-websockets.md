<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./205-schedule-and-queues.md">Previous : Schedule and queues</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./999-large-upload.md">Next : Large upload</a></div></td></tr></table>

# Websockets

PHP cannot hold a socket open while it answers requests, so Cube splits the job in three : your
**backend** stays a normal request-response application, a long-running **HTTP + Socket** process
holds the connections, and your **frontend** subscribes to it.

```
 _________           ________________________           __________
| Backend |-------->| (HTTP + Socket) Server |<------->| Frontend |
|_________|         |________________________|         |__________|
```

A signal travels like this

0. the frontend subscribes to a channel on the **HTTP + Socket** server
1. your **backend** posts a signal to that server's HTTP side
2. the server routes it to the matching **channel**
3. the channel forwards it to every frontend subscribed to that path

The backend never talks to the browser directly — it talks to the socket process, which is the only
long-lived thing in the picture.

## Configuration

Two configuration elements, because two processes are involved. `WebsocketConfiguration` is read by
the socket process — it says what to bind. `BroadcastConfiguration` is read by your backend — it says
where to reach it.

```php
new WebsocketConfiguration(
    // where the frontend connects
    websocketHost: '0.0.0.0',
    websocketPort: 8088,
    // where the backend connects — keep this one private
    httpHost: '0.0.0.0',
    httpPort: 8089,
    // shared by the socket process and Broadcast, which sends it with every signal
    broadcastSecret: env('WEBSOCKET_SECRET'),
),

new BroadcastConfiguration(
    // how the backend reaches the socket process
    // with containers, this is the service name
    httpHost: 'websocket',
    // null re-uses WebsocketConfiguration's httpPort
    httpPort: null,
),
```

`BroadcastConfiguration` also takes `socketHost` and `socketPort`, used when building the URL the
frontend should connect to — set them when the address your browser sees differs from the one the
process binds, which is the normal case behind a reverse proxy.

The HTTP side is what makes a signal appear on every browser. With a `broadcastSecret`, it answers
`403` to any request that does not carry it in the `X-Broadcast-Secret` header — `Broadcast` adds it
for you. Without one, anyone who can reach it can emit on any channel, and the server logs a warning
when it starts. Either way, expose only the websocket port publicly.

## Creating a channel

A channel is a class with a route. The route's slugs are what separate one stream from another.

```php
class JobChannel extends Channel
{
    public function getRoute(): string
    {
        return '/job/{id}';
    }
}
```

Channels are discovered by class, so the file is enough — nothing to register.

### Deciding who may subscribe

Override `authorize()` to accept or refuse a subscription. It runs before the connection joins, and
receives the slug values of the path being subscribed to. Return `null` to allow, or a string, which
is sent back to the client as the reason.

```php
class JobChannel extends Channel
{
    public function getRoute(): string
    {
        return '/job/{id}';
    }

    public function authorize(array $slugs = []): ?string
    {
        [$jobId] = $slugs;

        if (!Authentication::getInstance()->isLogged())
            return 'Authentication required';

        if (!Job::find($jobId))
            return 'Unknown job';

        return null;
    }
}
```

Without an override every subscription is accepted, so write this one before exposing anything
sensitive.

## Emitting from your backend

Ask for the channel as a dependency and call `emit()` with the payload and the route parameters.

```php
class JobRunner
{
    public function __construct(
        protected JobChannel $jobChannel
    ) {}

    public function process(int $jobId): void
    {
        // ...
        $this->jobChannel->emit(
            ['status' => 'ended', 'exitCode' => $exitCode],
            [$jobId]
        );
    }
}
```

`emit()` returns a `bool` : whether the socket process accepted the signal. It is not a delivery
receipt — a channel with no subscriber accepts happily.

When you send many signals to the same path, lock the parameters once instead of rebuilding the path
each time

```php
$this->jobChannel->lockParams([$jobId]);

$this->jobChannel->emit(['status' => 'started']);
$this->jobChannel->emit(['progress' => 50]);
$this->jobChannel->emit(['status' => 'ended']);

$this->jobChannel->unlockParams();
```

While locked, the parameters passed to `emit()` are ignored — that is the point, and also the trap :
unlock before emitting on another path.

## Pointing the frontend at a channel

`path()` gives you the channel path, and `redirect()` returns a `307` to the full websocket URL,
built from your `BroadcastConfiguration`

```php
class JobController extends Controller
{
    public static function follow(Request $request, int $id, JobChannel $jobChannel)
    {
        return $jobChannel->redirect([$id]);
    }
}
```

The client ends up connecting to something like `ws://websocket:8088/job/394898`, and every `emit()`
on those parameters lands there as JSON. Cube adds a `__class` key holding the channel class name, so
a frontend listening to several channels can tell them apart.

## Running the server

```sh
php do websocket:serve

# -l or --log echoes the server log to stdout, which is what you want as a service
php do websocket:serve -l
```

It is a long-running process : give it a supervisor, one service, and keep its HTTP port off the
public network.

```yaml
services:
  websocket:
    image: my-app
    command: ["php", "do", "websocket:serve", "-l"]
    ports:
      - "8088:8088"   # frontend
    expose:
      - "8089"        # backend only
    restart: always
```
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./205-schedule-and-queues.md">Previous : Schedule and queues</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./999-large-upload.md">Next : Large upload</a></div></td></tr></table>
