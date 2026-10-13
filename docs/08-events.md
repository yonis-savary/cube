<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./07-collections.md">Previous : Collections</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./09-logging.md">Next : Logging</a></div></td></tr></table>

# Events

An event is a small object carrying what happened ; a dispatcher holds the callbacks waiting for it.
Cube has no global bus that everything routes through : `Events` is the application-wide dispatcher
component, and objects that need to be observed carry their own subscription list.

```php
// App/Requires/listeners.php
LoggedInUser::on(function (LoggedInUser $event) {
    Logger::getInstance()->info('{user} signed in', ['user' => $event->userId]);
});
```

Subscriptions have to be registered before anything can fire, so they belong in a file that boots
with your application : a `Requires/` or `Includes/` file of your application directory, as
described in [Applications and discovery](./04-applications-and-discovery.md).

## Listening

An event class subscribes for itself with its static `on()`, on `Events` unless you pass another
dispatcher

```php
LoggedInUser::on(fn (LoggedInUser $event) => $audit->record($event->userId));
LoggedInUser::on(fn (LoggedInUser $event) => $audit->record($event->userId), $myOwnDispatcher);
```

You can also subscribe on the dispatcher directly. `on()` takes the event name and a callback, and
returns the dispatcher so you can chain

```php
Events::getInstance()
    ->on(FailedAuthentication::class, fn (FailedAuthentication $event) => $audit->failure($event->identifier))
    ->on(LoggedOutUser::class, fn (LoggedOutUser $event) => $sessions->close($event->userId))
;
```

The event name is the class name, so type the parameter and your IDE knows what it holds. Several
callbacks can subscribe to the same event ; they run in registration order.

## Dispatching your own

Extend `Event`, put what listeners need in the constructor, and call `dispatch()`

```php
class ProductWentOutOfStock extends Event
{
    public function __construct(
        public Product $product,
        public int $lastQuantity
    ) {}
}

(new ProductWentOutOfStock($product, 0))->dispatch();
```

`dispatch()` sends to the `Events` component by default. Pass a dispatcher to send it somewhere else

```php
(new ProductWentOutOfStock($product, 0))->dispatch($myOwnDispatcher);
```

## Stopping an event

A listener can call `prevent()` on the event : the listeners after it are skipped, `dispatch()`
returns `false`, and the same dispatcher then sends a `PreventedEvent` carrying the stopped event

```php
DisplayingResponse::on(function (DisplayingResponse $event) {
    if ($event->response->getStatusCode() === 503)
        $event->prevent(); // the response is not sent
});

PreventedEvent::on(fn (PreventedEvent $prevented) => Logger::getInstance()->notice(
    '{event} was prevented',
    ['event' => $prevented->event::class]
));
```

Your own code decides what a prevented event means, from the value `dispatch()` returns

```php
if (!(new ProductWentOutOfStock($product, 0))->dispatch())
    return;
```

`isPrevented()` tells whether an event was stopped.

## Events without a class

When a class would be ceremony, dispatch a name. Cube wraps it in a `CustomEvent`, whose `data`
property carries whatever you attached

```php
Events::getInstance()->on('cache-warmed', function (CustomEvent $event) {
    Logger::getInstance()->info('Warmed {count} entries', ['count' => $event->data]);
});

Events::getInstance()->dispatch(new CustomEvent('cache-warmed', 1240));
```

Dispatching a bare string works too : `dispatch('cache-warmed')` builds the `CustomEvent` for you,
with a `null` payload.

`CustomEvent::on()` does not work : it subscribes to the `CustomEvent` class name, while a
`CustomEvent` is dispatched under its own name. Subscribe with `Events::getInstance()->on('name', ...)`.

## Objects that dispatch on themselves

`EventDispatcher` is a base class, not a service. Anything extending it owns its subscriptions, so
coordination stays visible in the object that causes it rather than in a global registry.

Two framework classes do this, and it changes where you subscribe.

**A model** dispatches `SavedModel` on itself, so you subscribe on the instance you care about

```php
$product = new Product(['name' => 'screen']);

$product->onSaved(function (SavedModel $event) {
    Logger::getInstance()->info('{name} saved', ['name' => $event->created->name]);
});

$product->save();
```

Subscribing to `SavedModel` on `Events` never fires : the model never sends it there.

**A logger** dispatches `LoggedMessage` on itself for every line it writes. That is how a logger
forwards to another one (see [Logging](./09-logging.md#forwarding-to-another-logger)), and you can
hook the same way

```php
Logger::getInstance()->on(LoggedMessage::class, function (LoggedMessage $event) use ($pager) {
    if ('critical' === $event->level)
        $pager->page($event->message);
});
```

You can give any of your own classes the same ability by extending `EventDispatcher`.

## Dispatching from the command line

`php do cube:dispatch` dispatches an event on `Events`. Give the class name, or the end of it

```sh
php do cube:dispatch --event PostDeployment
php do cube:dispatch -e "App\Events\CatalogImported"
```

The event is built through the [`Injector`](./05-dependency-injection.md), so its constructor
parameters must be resolvable. The command answers `1` without `--event`, `2` when no `Event` class
matches.

## What the framework dispatches

On the `Events` component

| Event | Fired when | Carries |
|---|---|---|
| `FrameworkLoaded` | the autoloader is initialized, **before** your `Requires/` files run | — |
| `ApplicationsLoaded` | your applications are loaded and their `Requires/` files included | `loaded` (the application paths) |
| `PreRouting` | `Public/index.php` is about to build the request and route it (web entry point only) | — |
| `PostRouting` | the router answered, before the response is displayed (web entry point only) | `router`, `request`, `response` |
| `DisplayingResponse` | a response is about to be sent ; preventing it sends nothing | `response` |
| `PostDeployment` | you dispatch it after a deployment (`php do cube:dispatch --event PostDeployment`) ; Cube empties its class index cache, by clearing the whole `Cache` driver (application entries and cached routes included) | — |
| `AuthenticatedUser` | a user passed authentication | `authenticatedUser`, `userId` |
| `LoggedInUser` | a user session was opened | `authenticatedUser`, `userId` |
| `LoggedOutUser` | a user session was closed | `authenticatedUser`, `userId` |
| `FailedAuthentication` | credentials were rejected | `identifier` (the login tried) |
| `RememberedUser` | a user was restored from a remember-me token | `userData`, `userPrimaryKeyValue` |
| `GeneratedModels` | `php do models:generate` finished | — |
| `SocketServerSetup` | the [unix socket server](./31-unix-socket-server.md) is about to listen | `router` |
| `PreventedEvent` | a listener called `prevent()` on another event | `event` |

`FrameworkLoaded` fires before your listeners exist : an application cannot hear it. `PreRouting`
and `PostRouting` are dispatched by `server/Public/index.php`, not by the router : a request served
another way (unix socket server, `CubeTestCase`) does not fire them.

On the object itself

| Event | Dispatched by | Carries |
|---|---|---|
| `SavedModel` | the `Model` instance that was saved | `created`, `database` |
| `LoggedMessage` | the `Logger` that wrote the line | `level`, `message`, `context` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./07-collections.md">Previous : Collections</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./09-logging.md">Next : Logging</a></div></td></tr></table>
