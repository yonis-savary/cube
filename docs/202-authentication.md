<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./201-static-server.md">Previous : Static server</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./203-user-component.md">Next : User component</a></div></td></tr></table>

# Authentication

`Authentication` answers one question — who is making this request — and keeps the answer in a
session scoped to itself. It does not know how credentials are checked : that is an
`AuthenticationProvider`, and `PasswordAuthentication` is the one shipped with Cube.

Declare the provider in your `cube.php`

```php
new AuthenticationConfiguration(
    new PasswordAuthentication(
        model: AppUser::class,
        loginFields: ['email', 'login'],
        passwordField: 'password_hash',
    )
)
```

Then a login route is three lines

```php
public static function login(LoginRequest $request)
{
    if (!Authentication::getInstance()->attempt($request->validated('email'), $request->validated('password')))
        return Response::unauthorized('Wrong credentials');

    return Response::ok();
}
```

## Who is logged in

```php
$auth = Authentication::getInstance();

$auth->isLogged();   // bool
$auth->userId();     // the primary key, false when nobody is logged in
$auth->user();       // the user Model
```

`user()` rebuilds the model from what was stored in the session, so it costs no query. It **throws**
when nobody is logged in — guard with `isLogged()` rather than catching. Being a snapshot taken at
login, it does not see later changes : read roles or permissions you may revoke from the database
(`User::find($auth->userId())`), not from `user()`.

`login()` and `logout()` give the session a new id, so an id planted before login is worth nothing
after it — provided nothing was printed before the call, otherwise the id is kept and Cube logs a
warning. The session cookie is `HttpOnly`, `Secure` over HTTPS (behind a proxy, when it sends
`X-Forwarded-Proto: https`), and `SameSite=Lax`. Change them through the driver

```php
// force Secure when the proxy in front of you sends no X-Forwarded-Proto
new SessionConfiguration(driver: new PHPGlobalSession(sameSite: 'Strict', secureCookie: true))
```

Cube has no CSRF token : cross-site requests are kept away by `SameSite=Lax`, which stops the
browser from sending the session cookie with a `POST`, `PUT` or `DELETE` coming from another site.
Two consequences : never change state on a `GET` route (nor on `Route::any()`, which accepts `GET`),
and remember that subdomains of your site count as the *same* site — if they host content you do not
control, add a token check of your own. The same goes for a session cookie set to `SameSite=None`,
which a cross-site request carries again.

A body is only read as JSON when its `Content-Type` is exactly `application/json` (or `+json`) :
`text/plain; x=application/json`, which a browser sends cross-site without asking first, stays a
plain text body.

`login()` throws a `RuntimeException` once output has started : the session id can no longer be
regenerated then, and a visitor who planted theirs before the login would share the new session.

| Method | Does |
|---|---|
| `attempt(string $login, ?string $password = null)` | checks credentials through the provider and opens the session |
| `login(Model $user)` | opens the session for a user you already have |
| `loginById(mixed $id)` | loads the user by primary key, then logs them in |
| `logout()` | closes the session, does nothing when nobody is logged in |
| `isLogged()` / `user()` / `userId()` | read the current session |

`login()` is the one to use after a registration, or after any check of your own — it skips the
provider entirely.

## Password authentication

`PasswordAuthentication` looks the user up by any of the `loginFields`, then verifies the password
with PHP's `password_verify()`. Store your hashes with `password_hash()` and it will match.

```php
new PasswordAuthentication(
    model: AppUser::class,
    loginFields: 'email',            // one field, or a list of them
    passwordField: 'password_hash',
    saltField: 'password_salt',      // optional
)
```

| Argument | Does |
|---|---|
| `$model` | the model holding your users, must extend `Model` |
| `$loginFields` | field or fields the identifier is matched against, joined with `OR` |
| `$passwordField` | field holding the hash |
| `$saltField` | when set, that column's value is appended to the password before verifying |

A `saltField` means the stored hash was computed over `password + salt`, so hash it the same way when
you create the user.

The models `attempt()` and `userById()` return come without their `passwordField` and `saltField`, so
the copy kept in the session never holds a hash. Use `find()` when you need those columns.

### Your own provider

Implement `AuthenticationProvider` — two methods — and pass it to the configuration instead. This is
the way in for an LDAP directory, an API token, or a single sign-on.

```php
class TokenAuthentication implements AuthenticationProvider
{
    public function attempt(string $identifier, ?string $password = null): Model|false
    {
        return AppUser::findWhere(['api_token' => $identifier]) ?? false;
    }

    public function userById(mixed $id): Model|false
    {
        return AppUser::find($id) ?? false;
    }
}
```

## Remembering a user across sessions

`RememberMe` is a middleware that logs a user back in from a cookie. It sets a random token (64
hexadecimal characters from `random_bytes()`) as a cookie, and keeps only its SHA-256 hash in the
cache, pointing at the user id : reading the cache never gives out a working cookie. A cookie of any
other shape is ignored.

Add it to the routes that should accept it — usually as a common middleware

```php
new RouterConfiguration(
    commonMiddlewares: [RememberMe::class]
)
```

The middleware only acts when nobody is logged in, so it never overrides a real session.

Issuing the cookie is your call — nothing does it automatically. The natural place is a listener on
the authentication event, so every successful login gets remembered

```php
Events::getInstance()->on(AuthenticatedUser::class, function (AuthenticatedUser $event) {
    RememberMe::getInstance()->register($event);
});
```

And on logout, drop the token so the cookie cannot be replayed

```php
RememberMe::getInstance()->forget($request);
```

`forget()` takes the `Request` — it reads the cookie itself — or the token directly.

When a user changes their password, or asks to be signed out everywhere, revoke the tokens of
every device at once

```php
RememberMe::getInstance()->forgetUser($user);
```

Every token carries a generation of its user, and `forgetUser()` starts a new one : every token issued
before stops working at once, those of two devices logging in at the same moment included.

| Option of `UserRegisterConfiguration` | Default | Does |
|---|---|---|
| `cookieName` | `remember-me-token` | name of the cookie |
| `cookieDuration` | 2 weeks | lifetime, in seconds, of both cookie and cache entry |
| `refreshTokenOnRemember` | `true` | issue a fresh token each time one is used, the used one stops working |
| `cookieSecure` | `true` | HTTPS only |
| `cookieHttpOnly` | `true` | hide the cookie from JavaScript |
| `cookiePath` | `/` | path the cookie is sent for |
| `cookieSameSite` | `Lax` | `SameSite` attribute of the cookie |

Since the token lives in the cache, clearing the cache logs everybody's cookie out — that is your
emergency switch.

## Reacting to authentication

Five events are dispatched on the `Events` component. Subscribing to them is how you write an audit
trail or a brute-force counter without touching the login route — see [Events](./109-events.md).

| Event | Fired when | Carries |
|---|---|---|
| `AuthenticatedUser` | a session was opened for a user | `authenticatedUser`, `userId` |
| `LoggedInUser` | same moment, after `AuthenticatedUser` | `authenticatedUser`, `userId` |
| `LoggedOutUser` | `logout()` closed a session | `authenticatedUser`, `userId` |
| `FailedAuthentication` | credentials were rejected | `identifier`, the login that was tried |
| `RememberedUser` | a user was restored from a remember-me cookie | `userData`, `userPrimaryKeyValue` |

`FailedAuthentication` fires once per rejected attempt, whether the login is unknown or the password
wrong. Cube has no rate limiting of its own : count the failures per `identifier` in a listener (in the
`Cache`, for example) and refuse the attempt in your login route once a threshold is reached.
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./201-static-server.md">Previous : Static server</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./203-user-component.md">Next : User component</a></div></td></tr></table>
