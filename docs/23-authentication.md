<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./22-sessions.md">Previous : Sessions</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./24-user-component.md">Next : User component</a></div></td></tr></table>

# Authentication

The `Authentication` component answers one question — who is making this request — and keeps the
answer in a session scoped to itself. It does not know how credentials are checked : that is an
`AuthenticationProvider`, and `PasswordAuthentication` is the one shipped with Cube.

Declare the provider in your `cube.php`

```php
new AuthenticationConfiguration(
    new PasswordAuthentication(
        model: User::class,
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

`AuthenticationConfiguration` has no default : its `provider` argument is required, so the element
must be in your configuration before anything calls `Authentication::getInstance()`. Without it,
the first call fails while building the configuration, with an error about a missing constructor
argument rather than one naming the real cause.

## Who is logged in

Here is how you read the current session

```php
$auth = Authentication::getInstance();

$auth->isLogged();   // bool
$auth->userId();     // the primary key, false when nobody is logged in
$auth->user();       // the user Model
```

`user()` rebuilds the model from what was stored in the session, so it costs no query. It **throws**
a `RuntimeException` when nobody is logged in — guard with `isLogged()` rather than catching. Being
a snapshot taken at login, it does not see later changes : read roles or permissions you may revoke
from the database (`User::find($auth->userId())`), not from `user()`.

## Logging in and out

| Method | Does |
|---|---|
| `attempt(string $login, ?string $password = null)` | checks credentials through the provider and opens the session, `false` when rejected |
| `login(Model $user)` | opens the session for a user you already have |
| `loginById(mixed $id)` | loads the user through the provider, then logs them in. `false` when the provider finds nobody |
| `logout()` | closes the session, does nothing when nobody is logged in |
| `isLogged()` / `user()` / `userId()` | read the current session |

`login()` is the one to use after a registration, or after any check of your own — it skips the
provider entirely

```php
$user = User::fromArray([
    'email' => $request->validated('email'),
    'password_hash' => password_hash($request->validated('password'), PASSWORD_DEFAULT),
]);
$user->save();

Authentication::getInstance()->login($user);
```

`login()` stores the model as you give it : here the session holds the hash too. Use `loginById()`
instead when that matters, it goes through the provider, which leaves the credentials out.

`attempt()` first forgets whoever was logged in, so a failed attempt leaves nobody logged in. It does
not dispatch `LoggedOutUser` for that.

`login()` and `logout()` give the session a new id, so an id planted before login is worth nothing
after it. That is only possible before any output : `login()` throws a `RuntimeException` once
output has started (outside the CLI), since a visitor who planted their id before the login would
otherwise share the new session. The session cookie itself is described in
[Sessions](./22-sessions.md#cookie-and-security).

## Password authentication

`PasswordAuthentication` looks the user up by any of the `loginFields`, then verifies the password
with PHP's `password_verify()`. Store your hashes with `password_hash()` and they match.

```php
new PasswordAuthentication(
    model: User::class,
    loginFields: 'email',            // one field, or a list of them
    passwordField: 'password_hash',
    saltField: 'password_salt',      // optional
)
```

| Argument | Does |
|---|---|
| `$model` | the model holding your users, must extend `Model` (an `InvalidArgumentException` otherwise) |
| `$loginFields` | field or fields the identifier is matched against, joined with `OR` |
| `$passwordField` | field holding the hash |
| `$saltField` | when set, that column's value is appended to the password before verifying |

A `saltField` means the stored hash was computed over `password + salt`, so hash it the same way when
you create the user

```php
$user->password_hash = password_hash($password.$user->password_salt, PASSWORD_DEFAULT);
```

An attempt is rejected when

- no user matches the identifier, or **more than one** does (an `email` of one user equal to the
  `login` of another, for instance)
- the user's `passwordField` is empty
- `password_verify()` fails

An unknown login still costs one password verification, like a known one : the response time does
not tell whether a login exists.

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
        return User::findWhere(['api_token' => $identifier]) ?? false;
    }

    public function userById(mixed $id): Model|false
    {
        return User::find($id) ?? false;
    }
}

// cube.php
new AuthenticationConfiguration(new TokenAuthentication())
```

## Restricting routes

`Authentication` tells who the user is ; deciding what they may reach is the job of a middleware.
`AuthenticationMiddleware` is a ready-made base class for permission checks, described in
[Routing and middlewares](./11-routing-and-middlewares.md#guarding-a-group-with-permissions).

To keep users logged in after their session ends, see [Remember me](./25-remember-me.md). To give
each user their own instance of a service, see [User component](./24-user-component.md).

## Cross-site requests

Cube has no CSRF token : cross-site requests are kept away by `SameSite=Lax`, which stops the
browser from sending the session cookie with a `POST`, `PUT` or `DELETE` coming from another site.
Two consequences :

- never change state on a `GET` route (nor on `Route::any()`, which accepts `GET`)
- subdomains of your site count as the *same* site — if they host content you do not control, add a
  token check of your own. The same goes for a session cookie set to `SameSite=None`, which a
  cross-site request carries again.

A body is only read as JSON when its media type is exactly `application/json` (or
`application/...+json`) : `text/plain; x=application/json`, which a browser sends cross-site without
asking first, stays a plain text body.

## Reacting to authentication

Four events are dispatched on the `Events` component. Subscribing to them is how you write an audit
trail or a brute-force counter without touching the login route — see [Events](./08-events.md).

```php
LoggedInUser::on(function (LoggedInUser $event) {
    Logger::getInstance()->info('{user} signed in', ['user' => $event->userId]);
});
```

| Event | Fired when | Carries |
|---|---|---|
| `AuthenticatedUser` | a session was opened for a user | `authenticatedUser`, `userId` |
| `LoggedInUser` | same moment, right after `AuthenticatedUser` | `authenticatedUser`, `userId` |
| `LoggedOutUser` | `logout()` closed a session | `authenticatedUser`, `userId` |
| `FailedAuthentication` | `attempt()` rejected the credentials | `identifier`, the login that was tried |

`RememberMe` adds a fifth, `RememberedUser` — see [Remember me](./25-remember-me.md#reacting-to-a-remembered-user).

`FailedAuthentication` fires once per rejected attempt, whether the login is unknown or the password
wrong. Cube has no rate limiting of its own : count the failures per `identifier` in a listener (in the
`Cache`, for example) and refuse the attempt in your login route once a threshold is reached

```php
FailedAuthentication::on(function (FailedAuthentication $event) {
    $failures = Cache::getInstance()->child('login-failures');
    $failures->set($event->identifier, $failures->get($event->identifier, 0) + 1, Cache::HOUR);
});
```

## Reference

| Option of `AuthenticationConfiguration` | Default | Does |
|---|---|---|
| `provider` | none, required | the `AuthenticationProvider` checking credentials |

| Method of `AuthenticationProvider` | Returns |
|---|---|
| `attempt(string $identifier, ?string $password = null)` | the user `Model`, or `false` when the credentials are rejected |
| `userById(mixed $id)` | the user `Model`, or `false` when nobody has that id |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./22-sessions.md">Previous : Sessions</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./24-user-component.md">Next : User component</a></div></td></tr></table>
