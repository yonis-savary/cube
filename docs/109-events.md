<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./108-storage-and-caching.md">Previous : Storage and caching</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./110-database-and-queries.md">Next : Database and queries</a></div></td></tr></table>

# Events

An event is a small object carrying what happened ; a dispatcher holds the callbacks waiting for it.
Cube has no global bus that everything routes through — `Events` is the application-wide dispatcher,
and objects that need to be observed carry their own subscription list.

```php
Events::getInstance()->on(LoggedInUser::class, function (LoggedInUser $event) {
    info('{user} signed in', ['user' => $event->userId]);
});
```

Subscriptions have to be registered before anything can fire, so they belong in a file that boots
with your application — a `Requires/` or `Includes/` file of your application directory, as described
in [Applications](./103-applications.md).

## Listening

`on()` takes the event name and a callback, and returns the dispatcher so you can chain.

```php
Events::getInstance()
    ->on(FailedAuthentication::class, fn () => warning('Failed login attempt'))
    ->on(LoggedOutUser::class, fn (LoggedOutUser $event) => $sessions->close($event->userId))
;
```

The event name is the class name, so type-hint the parameter and your IDE knows what it holds.
Several callbacks can subscribe to the same event ; they run in registration order.

An event class can also subscribe for itself with its static `on()`, on `Events` unless you pass
another dispatcher.

```php
LoggedInUser::on(function (LoggedInUser $event) {
    info('{user} signed in', ['user' => $event->userId]);
});
```

This does not work for `CustomEvent` : it subscribes to the class name, while a `CustomEvent` is
dispatched under its own name.

## Dispatching your own

Extend `Event`, put what listeners need in the constructor, and call `dispatch()`.

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

## Events without a class

When a class would be ceremony, dispatch a name. Cube wraps it in a `CustomEvent`, whose `data`
property carries whatever you attached.

```php
Events::getInstance()->on('cache-warmed', function (CustomEvent $event) {
    info('Warmed {count} entries', ['count' => $event->data]);
});

Events::getInstance()->dispatch(new CustomEvent('cache-warmed', 1240));
```

Dispatching a bare string works too — `dispatch('cache-warmed')` builds the `CustomEvent` for you,
with a `null` payload.

## Objects that dispatch on themselves

`EventDispatcher` is a base class, not a service. Anything extending it owns its subscriptions, so
coordination stays visible in the object that causes it rather than in a global registry.

Two framework classes do this, and it changes where you subscribe.

**A model** dispatches `SavedModel` on itself, so you subscribe on the instance you care about

```php
$product = new Product(['name' => 'screen']);

$product->onSaved(function (SavedModel $event) {
    info('{name} saved', ['name' => $event->created->name]);
});

$product->save();
```

Subscribing to `SavedModel` on `Events` would never fire — the model never sends it there.

**A logger** dispatches `LoggedMessage` on itself for every line it writes. That is how a logger
forwards to another one, and you can hook the same way

```php
Logger::getInstance()->on(LoggedMessage::class, function (LoggedMessage $event) {
    if ('critical' === $event->level)
        $pager->page($event->message);
});
```

You can give any of your own classes the same ability by extending `EventDispatcher`.

## What the framework dispatches

On the `Events` component

| Event | Fired when | Carries |
|---|---|---|
| `AuthenticatedUser` | a user passed authentication | `authenticatedUser`, `userId` |
| `LoggedInUser` | a user session was opened | `authenticatedUser`, `userId` |
| `LoggedOutUser` | a user session was closed | `authenticatedUser`, `userId` |
| `FailedAuthentication` | credentials were rejected | — |
| `RememberedUser` | a user was restored from a remember-me token | `userData`, `userPrimaryKeyValue` |
| `GeneratedModels` | `php do models:generate` finished | — |
| `SocketServerSetup` | the [unix socket server](./207-unix-socket-server.md) is about to listen | `router` |

On the object itself

| Event | Dispatched by | Carries |
|---|---|---|
| `SavedModel` | the `Model` instance that was saved | `created`, `database` |
| `LoggedMessage` | the `Logger` that wrote the line | `level`, `message`, `context` |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./108-storage-and-caching.md">Previous : Storage and caching</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./110-database-and-queries.md">Next : Database and queries</a></div></td></tr></table>
