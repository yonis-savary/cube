<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./22-sessions.md">Previous : Sessions</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./24-user-component.md">Next : User component</a></div></td></tr></table>

# Authentication

The `Authentication` component answers one question — who is making this request — and keeps the
answer in a session scoped to itself, or for the request only when an API client sends a
[bearer token](#bearer-tokens). It does not know where users are stored nor how credentials
are checked : that is an `AuthenticationProvider`. Cube ships two, `PasswordAuthentication` for a
login form and `ModelAuthentication` for applications whose users sign in through
[single sign-on](./26-single-sign-on.md).

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
the first call throws a `RuntimeException` asking you to declare `AuthenticationConfiguration` in
`cube.php`.

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
| `attempt(string $login, ?string $password = null)` | checks credentials through the provider and opens the session, `false` when rejected. Throws a `RuntimeException` when the provider does not implement `VerifiesCredentials` |
| `login(Model $user)` | opens the session for a user you already have |
| `loginById(mixed $id)` | loads the user through the provider, then logs them in. `false` when the provider finds nobody |
| `logout()` | forgets the bearer user, closes the session, does nothing when nobody is logged in |
| `attemptBearerToken(string $token)` | authenticates the current request only, see [Bearer tokens](#bearer-tokens) |
| `isLogged()` / `user()` / `userId()` | read the bearer user of the request when there is one, the current session otherwise |

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

`login()` leaves the fields the provider names in `getCredentialFields()` out of the session copy :
here `user()` gives the user back without `password_hash`. The model you pass is left untouched.

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

The models `attempt()` and `userById()` return come without their `passwordField` and `saltField`, the
fields `getCredentialFields()` names. Use `find()` when you need those columns.

## Without passwords

`ModelAuthentication` finds users by primary key and checks nothing. Use it when users sign in
through [single sign-on](./26-single-sign-on.md), a magic link, or any check of your own followed
by `login()`

```php
new AuthenticationConfiguration(new ModelAuthentication(User::class))
```

`login()`, `loginById()` and [Remember me](./25-remember-me.md) work as usual. `attempt()` throws a
`RuntimeException`, since there is no credential to check.

### Your own provider

Implement `AuthenticationProvider` and pass it to the configuration instead. Add
`VerifiesCredentials` when it can check an identifier and a secret, which is what `attempt()` needs.
This is the way in for an LDAP directory

```php
class LdapAuthentication implements AuthenticationProvider, VerifiesCredentials
{
    public function attempt(string $identifier, ?string $password = null): Model|false
    {
        // an empty password makes an anonymous bind, which most directories accept
        if ('' === (string) $password || !@ldap_bind(ldap_connect('ldaps://directory.example.com'), $identifier, $password))
            return false;

        return User::findWhere(['login' => $identifier]) ?? false;
    }

    public function userById(mixed $id): Model|false
    {
        return User::find($id) ?? false;
    }

    public function getCredentialFields(): array
    {
        return [];
    }
}

// cube.php
new AuthenticationConfiguration(new LdapAuthentication())
```

For API tokens, add `VerifiesBearerTokens` instead, described in [Bearer tokens](#bearer-tokens).

## Bearer tokens

An API client authenticates each request with an `Authorization: Bearer <token>` header. The
`BearerAuthentication` middleware reads it and asks the provider who owns the token ; the user is
known for that request only, nothing is written to the session and no cookie is sent.

Give your provider the `VerifiesBearerTokens` capability. Issuing, storing and revoking the tokens
stays in your application : generate them with `Identifier::prefixed()` and store their
`Identifier::hash()`, never the token itself (see [Identifiers and secrets](./29-identifiers-and-secrets.md))

```php
class ApiAuthentication extends PasswordAuthentication implements VerifiesBearerTokens
{
    public function userByBearerToken(string $token): Model|false
    {
        $owner = ApiToken::findWhere(['hash' => Identifier::hash($token)])?->user_id;

        return $owner ? $this->userById($owner) : false;
    }
}

// cube.php
new AuthenticationConfiguration(new ApiAuthentication(
    model: User::class,
    loginFields: 'email',
    passwordField: 'password_hash',
))
```

Then put the middleware in front of your API routes

```php
$router->group('/api', [BearerAuthentication::class], function: function (Router $router) {
    $router->addRoutes(
        Route::get('/products', [ProductController::class, 'list']),
    );
});
```

| The request carries | `BearerAuthentication` |
|---|---|
| no `Authorization` header, or another scheme (`Basic`...) | lets it through, unauthenticated : your route decides |
| `Bearer` and a token the provider recognizes | lets it through, `Authentication` answers for that user |
| `Bearer` and an unknown or empty token | answers `401` with `WWW-Authenticate: Bearer error="invalid_token"` |

The scheme name is case-insensitive (`bearer` works too). In a route, read the user as usual with
`isLogged()`, `user()` and `userId()` ; like `login()`, the user comes without the fields
`getCredentialFields()` names. The bearer user takes precedence over a session user for that
request, and `logout()` forgets it.

`attemptBearerToken()` dispatches no event : the [Remember me](./25-remember-me.md) listener
registers on `AuthenticatedUser`, and an API client has no use for a cookie. When `RememberMe` is a
common middleware too, list `BearerAuthentication` before it : `RememberMe` only looks for its
cookie when nobody is authenticated yet, so a bearer request never opens a session.

`Authentication` opens its session on first use, not when it is built : a request authenticated by
bearer token never starts one.

## Restricting routes

`Authentication` tells who the user is ; deciding what they may reach is the job of a middleware.
`AuthenticationMiddleware` is a ready-made base class for permission checks, described in
[Routing and middlewares](./11-routing-and-middlewares.md#guarding-a-group-with-permissions).

To keep users logged in after their session ends, see [Remember me](./25-remember-me.md). To give
each user their own instance of a service, see [User component](./24-user-component.md).

## Cross-site requests

Cube has no CSRF token : cross-site requests are kept away by `SameSite=Lax`, which stops the
browser from sending the session cookie with a `POST`, `PUT` or `DELETE` coming from another site.
Bearer tokens are not concerned : a browser never adds an `Authorization` header on its own.
Two consequences for the session :

- never change state on a `GET` route (nor on `Route::any()`, which accepts `GET`)
- subdomains of your site count as the *same* site — if they host content you do not control, add a
  token check of your own. The same goes for a session cookie set to `SameSite=None`, which a
  cross-site request carries again.

A body is only read as JSON when its media type is exactly `application/json` (or
`application/...+json`) : `text/plain; x=application/json`, which a browser sends cross-site without
asking first, stays a plain text body.

## Reacting to authentication

Four events are dispatched on the `Events` component, by the session methods only : a bearer
token dispatches none. Subscribing to them is how you write an audit
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
wrong. To refuse a login after too many failures, count them with a `RateLimiter` : see
[Rate limiting](./27-rate-limiting.md#limiting-login-attempts).

## Reference

| Option of `AuthenticationConfiguration` | Default | Does |
|---|---|---|
| `provider` | none, required | the `AuthenticationProvider` finding users |

| Method of `AuthenticationProvider` | Returns |
|---|---|
| `userById(mixed $id)` | the user `Model`, or `false` when nobody has that id |
| `getCredentialFields()` | the fields `login()` keeps out of the session, `[]` when there are none |

| Method of `VerifiesCredentials` | Returns |
|---|---|
| `attempt(string $identifier, ?string $password = null)` | the user `Model`, or `false` when the credentials are rejected |

| Method of `VerifiesBearerTokens` | Returns |
|---|---|
| `userByBearerToken(string $token)` | the user `Model`, or `false` when nobody owns the token |

| Method of `BearerAuthentication` | Does |
|---|---|
| `handleRequest(Request $request)` | authenticates the request, or answers `401` for a refused token |
| `tokenOf(Request $request)` (static) | the bearer token of the request, `''` when the header holds none, `null` without a `Bearer` header |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./22-sessions.md">Previous : Sessions</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./24-user-component.md">Next : User component</a></div></td></tr></table>
