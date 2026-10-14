<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./24-user-component.md">Previous : User component</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./26-encryption-and-signing.md">Next : Encryption and signing</a></div></td></tr></table>

# Remember me

`RememberMe` is a middleware that logs a user back in from a cookie once their session has ended. It
builds on [Authentication](./23-authentication.md) : the cookie only says *who*, the session does the
rest.

Add the middleware to the routes that should accept the cookie — usually every route, as a common
middleware

```php
new RouterConfiguration(
    commonMiddlewares: [RememberMe::class]
)
```

Then issue the cookie on every successful login, with a listener on the authentication event

```php
// App/Requires/listeners.php
AuthenticatedUser::on(function (AuthenticatedUser $event) {
    RememberMe::getInstance()->register($event);
});
```

And drop it on logout, so it cannot be replayed

```php
public static function logout(Request $request)
{
    Authentication::getInstance()->logout();
    RememberMe::getInstance()->forget($request);

    return Response::noContent();
}
```

## How it works

`register()` sets a random token (64 hexadecimal characters from `random_bytes()`) as a cookie, and
keeps only its SHA-256 hash in the `Cache`, pointing at the user id : reading the cache never gives
out a working cookie.

On each request, the middleware

1. does nothing when someone is already logged in, so it never overrides a real session
2. reads the cookie, and ignores it when it is not 64 hexadecimal characters or matches no stored hash
3. logs the user in with `Authentication::loginById()` — when the provider no longer finds the user,
   the token is deleted and the request goes on anonymously
4. dispatches `RememberedUser`
5. replaces the token with a fresh one, unless `refreshTokenOnRemember` is off (see below)

Since `loginById()` goes through `login()`, step 3 also dispatches `AuthenticatedUser` and
`LoggedInUser`, exactly like a login by password.

## Issuing the cookie

Nothing issues the cookie automatically : that is your call. The listener shown at the top of this
page remembers every login ; to remember only the users who tick a "remember me" checkbox, call
`register()` from your login route instead of the listener. It takes the `AuthenticatedUser` event,
or the user model directly

```php
public static function login(LoginRequest $request)
{
    $auth = Authentication::getInstance();

    if (!$auth->attempt($request->validated('email'), $request->validated('password')))
        return Response::unauthorized('Wrong credentials');

    if ($request->validated('remember'))
        RememberMe::getInstance()->register($auth->user());

    return Response::ok();
}
```

`register()` sends a `Set-Cookie` header : call it before any output.

## Forgetting a device

`forget()` takes the `Request` — it reads the cookie itself — or the token directly. It deletes the
token and expires the cookie in the browser, even when the request carried none.

```php
RememberMe::getInstance()->forget($request);
RememberMe::getInstance()->forget($token);
```

## Signing a user out everywhere

When a user changes their password, or asks to be signed out of every device, revoke all their tokens
at once

```php
RememberMe::getInstance()->forgetUser($user);    // a Model, or its primary key
```

Every token carries a generation of its user, and `forgetUser()` starts a new one : every token issued
before stops working at once, those of two devices logging in at the same moment included. Tokens
issued afterwards use the new generation and work normally.

## Token refresh

With `refreshTokenOnRemember` (the default), a used token stops working and the browser receives a new
one : a stolen cookie that was already used by its owner is worth nothing.

The middleware never issues two tokens for one request : when a listener already called `register()`
during the login of step 3 — the `AuthenticatedUser` listener shown at the top of this page does — that
token is the fresh one, and the used token is deleted.

With `refreshTokenOnRemember: false`, the used token keeps working until it expires : a `register()`
called during the login of step 3 does nothing, so the listener does not replace it either.

## Reacting to a remembered user

`RememberedUser` is dispatched each time a user is restored from a cookie, after the login. Listen to it
to tell those logins apart from the ones by password — see [Events](./08-events.md)

```php
RememberedUser::on(function (RememberedUser $event) {
    Logger::getInstance()->info('{user} came back through remember-me', ['user' => $event->userPrimaryKeyValue]);
});
```

| Event | Fired when | Carries |
|---|---|---|
| `RememberedUser` | a user was restored from a remember-me cookie | `userData` (the user `Model`), `userPrimaryKeyValue` |

## Where tokens live

Tokens live in `Cache::getInstance()`, under the `remember-me-tokens` and `remember-me-users` children,
for `cookieDuration` seconds. Two consequences :

- clearing the cache logs every remembered user out — that is your emergency switch
- with several servers, the cache must be shared between them (a `RedisCache`), or a cookie issued by
  one server is unknown to the others. See [Storage and caching](./21-storage-and-caching.md).

## Reference

Configure it with a `UserRegisterConfiguration` in your `cube.php`

```php
new UserRegisterConfiguration(
    cookieName: 'agency-remember-me',
    cookieDuration: Cache::WEEK * 4,
)
```

| Option of `UserRegisterConfiguration` | Default | Does |
|---|---|---|
| `cookieName` | `remember-me-token` | name of the cookie |
| `cookieDuration` | `Cache::WEEK * 2` (2 weeks) | lifetime, in seconds, of both the cookie and the cache entries |
| `refreshTokenOnRemember` | `true` | issue a fresh token each time one is used, the used one stops working |
| `cookieSecure` | `true` | send the cookie over HTTPS only. Over plain HTTP — a local server — the browser drops it, so set it to `false` there |
| `cookieHttpOnly` | `true` | hide the cookie from JavaScript |
| `cookiePath` | `/` | path the cookie is sent for |
| `cookieSameSite` | `Lax` | `SameSite` attribute of the cookie |

| Method of `RememberMe` | Does |
|---|---|
| `register(AuthenticatedUser\|Model $user)` | issues a token and sends its cookie |
| `forget(Request\|string $requestOrToken)` | deletes that token and expires the cookie |
| `forgetUser(Model\|int\|string $user)` | invalidates every token of that user |
| `handleRequest(Request $request)` | what the middleware runs, callable directly outside the router |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./24-user-component.md">Previous : User component</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./26-encryption-and-signing.md">Next : Encryption and signing</a></div></td></tr></table>
