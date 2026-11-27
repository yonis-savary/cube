<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./25-remember-me.md">Previous : Remember me</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./27-encryption-and-signing.md">Next : Encryption and signing</a></div></td></tr></table>

# Single sign on

`SingleSignOn` lets your users sign in with an account they already have (Google,
Microsoft, a company Keycloak...) through the OAuth 2.0 authorization code flow. It sends the user
to the provider and checks who came back. What happens next (finding or creating your user, then
opening the session with [Authentication](./23-authentication.md)) stays in your application.

Declare your providers in your `cube.php`

```php
new SingleSignOnConfiguration(
    OpenIDConnectProvider::google(
        clientId: env('GOOGLE_CLIENT_ID'),
        clientSecret: env('GOOGLE_CLIENT_SECRET'),
        redirectUri: env('APP_URL').'/auth/google/callback',
    ),
    new OpenIDConnectProvider(
        name: 'microsoft',
        issuer: 'https://login.microsoftonline.com/'.env('ENTRA_TENANT_ID').'/v2.0',
        clientId: env('ENTRA_CLIENT_ID'),
        clientSecret: env('ENTRA_CLIENT_SECRET'),
        redirectUri: env('APP_URL').'/auth/microsoft/callback',
    ),
),

// No password to check : users are found by id only
new AuthenticationConfiguration(new ModelAuthentication(User::class)),
```

Then two routes cover every provider

```php
class SingleSignOnController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/auth/{provider}', [self::class, 'redirect']),
            Route::get('/auth/{provider}/callback', [self::class, 'callback']),
        );
    }

    // Sends the user to the provider, 404 when no provider has that name
    public static function redirect($_, string $provider)
    {
        return SingleSignOn::getInstance()->redirect($provider);
    }

    public static function callback(Request $request, string $provider)
    {
        if (!$identity = SingleSignOn::getInstance()->callback($request, $provider))
            return Response::found('/login?error=sign-in-cancelled');

        Authentication::getInstance()->login(self::userOf($identity));

        return Response::found('/');
    }
}
```

Each `redirectUri` must be registered, character for character, as an authorized redirect URI in
the provider's console (Google Cloud console, Microsoft Entra admin center...).

## Linking an identity to your user

`callback()` gives an `ExternalIdentity` back

| Property | Holds |
|---|---|
| `provider` | the provider name, `google` |
| `id` | the user's id at that provider, which never changes |
| `email` | their email, `null` when the provider gave none |
| `emailVerified` | `true` only when the provider says it checked that email |
| `name` | their display name, or `null` |
| `raw` | the whole profile as the provider returned it (`picture`, `locale`, `login`...) |

Link it to your user through `provider` and `id`, never through the email : a user can change
their email at the provider, and two providers may give the same one to two different people.
Here is an example with a `user_identity` table (`user_id`, `provider`, `external_id`) next to
your `user` table

```php
protected static function userOf(ExternalIdentity $identity): User
{
    $link = UserIdentity::findWhere(['provider' => $identity->provider, 'external_id' => $identity->id]);
    if ($link)
        return User::find($link->user_id);

    $user = User::fromArray(['email' => $identity->email, 'name' => $identity->name]);
    $user->save();

    UserIdentity::fromArray([
        'user_id' => $user->id(),
        'provider' => $identity->provider,
        'external_id' => $identity->id,
    ])->save();

    return $user;
}
```

To attach a new identity to an account that already exists with the same email, check
`emailVerified` first. Otherwise anyone who types your user's email into a provider that does not
verify it gets into their account

```php
if ($identity->emailVerified && $user = User::findWhere(['email' => $identity->email]))
    // link $identity to $user instead of creating a new one
```

## When the callback gives nothing

`callback()` returns `null` when the visit cannot be trusted or the user changed their mind. Send
them back to your login page.

| Case | Why it is refused |
|---|---|
| the user refused consent (`?error=access_denied`) | there is no code |
| the callback URL was opened without going through `redirect()` first | nothing waits in the session : someone else's callback link opened in your browser would sign you in as them |
| the `state` does not match the one stored by `redirect()` | same reason, the request was forged or belongs to another tab |
| the callback is opened a second time (refresh, back button) | the pending authorization is used once |
| the provider refused the code (`invalid_grant`, expired or already used) | the user can simply start again |

A misconfiguration throws a `RuntimeException` instead, naming the provider and what it answered :
a client id or secret the provider rejects, an unreachable discovery document, a userinfo endpoint
that answers without a subject. Those are for you to fix, not for the user to retry.

An unknown provider name (`/auth/myspace`) aborts the request with a 404, through a
`ResponseException`.

## How it is checked

```
Browser                  Your app                       Provider
   │  GET /auth/google      │                               │
   │───────────────────────>│ state + PKCE verifier         │
   │                        │ stored in the session         │
   │  302 → authorization URL (state, code_challenge)       │
   │<───────────────────────│                               │
   │───────────────────────────────────────────────────────>│ sign in, consent
   │  302 → /auth/google/callback?code=...&state=...        │
   │<───────────────────────────────────────────────────────│
   │  GET /callback         │ state compared, then dropped  │
   │───────────────────────>│ POST token (code, secret, verifier)
   │                        │──────────────────────────────>│
   │                        │ GET userinfo (access token)   │
   │                        │──────────────────────────────>│
   │                        │<──────────────────────────────│
   │                        │ ExternalIdentity              │
```

- The `state` ties the callback to the browser that started the flow, and is compared in constant
  time.
- PKCE (`S256`) ties the code to the verifier kept in your session : a code stolen on its way
  back is worth nothing without it.
- The identity is read from the provider's API with the access token, not from an ID token. That
  token comes straight from the provider's token endpoint over TLS, which is the check OpenID
  Connect allows in place of the ID token signature in this flow. So Cube does not verify JWT
  signatures, and does not need a JWT library.
- The client secret only travels from your server to the token endpoint, never through the browser.

## OpenID Connect providers

`OpenIDConnectProvider` works with any provider that publishes a discovery document at
`{issuer}/.well-known/openid-configuration`. Cube reads its endpoints from there and keeps them a
day in the [Cache](./21-storage-and-caching.md). Here is an example with GitLab and a Keycloak
realm

```php
new OpenIDConnectProvider(
    name: 'gitlab',
    issuer: 'https://gitlab.com',
    clientId: env('GITLAB_CLIENT_ID'),
    clientSecret: env('GITLAB_CLIENT_SECRET'),
    redirectUri: env('APP_URL').'/auth/gitlab/callback',
),
new OpenIDConnectProvider(
    name: 'company',
    issuer: 'https://sso.example.com/realms/staff',
    clientId: env('KEYCLOAK_CLIENT_ID'),
    clientSecret: env('KEYCLOAK_CLIENT_SECRET'),
    redirectUri: env('APP_URL').'/auth/company/callback',
),
```

| Argument | Default | Does |
|---|---|---|
| `name` | none | the name used in your routes and in `ExternalIdentity::$provider` |
| `issuer` | none | the provider's issuer URL, exactly as its discovery document announces it |
| `clientId`, `clientSecret` | none | your application's credentials at the provider |
| `redirectUri` | none | your callback URL, as registered at the provider |
| `scopes` | `['openid', 'email', 'profile']` | the scopes asked for |

`OpenIDConnectProvider::google($clientId, $clientSecret, $redirectUri, $name = 'google')` fills in
Google's issuer.

The issuer announced by the discovery document must equal `issuer`, or the sign in throws. This
rules out Microsoft's multi-tenant `common` endpoint, whose document announces a
`{tenantid}` placeholder : give your tenant id.

## Your own provider

A provider with an authorization code flow and a profile endpoint is one class : extend
`AuthorizationCodeProvider` and name its endpoints. Cube handles the state, PKCE and the code
exchange. Here is an example with Discord

```php
class DiscordProvider extends AuthorizationCodeProvider
{
    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        parent::__construct('discord', $clientId, $clientSecret, $redirectUri, ['identify', 'email']);
    }

    protected function authorizationEndpoint(): string
    {
        return 'https://discord.com/oauth2/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://discord.com/api/oauth2/token';
    }

    protected function identityOf(string $accessToken, IdentityProviderClient $client): ExternalIdentity
    {
        $user = $client->get('https://discord.com/api/users/@me', headers: $this->bearer($accessToken))->getJSON();

        return new ExternalIdentity($this->name, $user['id'], $user['email'] ?? null, $user['verified'] ?? false, $user['global_name'] ?? null, $user);
    }
}
```

When the token endpoint answers a refused code with another error than `invalid_grant`, list it in
`REFUSED_CODE_ERRORS` so `callback()` gives `null` instead of throwing

```php
public const REFUSED_CODE_ERRORS = ['invalid_grant', 'bad_verification_code'];
```

Override `authorizationParameters()` to add parameters to the authorization URL (`prompt`, `hd`,
`login_hint`...). They cannot replace the ones the flow relies on, such as `state` or
`code_challenge`.

A provider must hold nothing but strings and arrays : `php do configuration:cache` serializes your
configuration, so no client object and no closure in its properties.

### Plugging in league/oauth2-client

Providers that are not OpenID Connect, such as GitHub or Facebook, are covered by
[league/oauth2-client](https://oauth2-client.thephpleague.com/providers/league/) and its many
providers. Implement `IdentityProvider` once to use any of them. A league provider holds a Guzzle client, which
cannot be serialized : keep its class and options instead, and build it when needed

```php
class LeagueIdentityProvider implements IdentityProvider
{
    /**
     * @param class-string<AbstractProvider> $class
     */
    public function __construct(
        protected string $name,
        protected string $class,
        protected array $options,  // clientId, clientSecret, redirectUri...
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function authorize(): PendingAuthorization
    {
        $league = $this->league();
        $url = $league->getAuthorizationUrl();  // league draws the state and the PKCE code here

        return new PendingAuthorization($url, $league->getState(), $league->getPkceCode());
    }

    public function identify(string $code, PendingAuthorization $pending): ?ExternalIdentity
    {
        $league = $this->league();
        $league->setPkceCode($pending->codeVerifier);

        try {
            $token = $league->getAccessToken('authorization_code', ['code' => $code]);
        } catch (IdentityProviderException $exception) {
            // GitHub calls a refused code bad_verification_code
            if (in_array($exception->getResponseBody()['error'] ?? null, ['invalid_grant', 'bad_verification_code'], true))
                return null;
            throw $exception;
        }

        $owner = $league->getResourceOwner($token);
        $profile = $owner->toArray();

        // league does not say whether the email was verified
        return new ExternalIdentity($this->name, (string) $owner->getId(), $profile['email'] ?? null, false, $profile['name'] ?? null, $profile);
    }

    protected function league(): AbstractProvider
    {
        return new ($this->class)($this->options + ['pkceMethod' => AbstractProvider::PKCE_METHOD_S256]);
    }
}

// cube.php
new SingleSignOnConfiguration(
    // composer require league/oauth2-github
    new LeagueIdentityProvider('github', Github::class, [
        'clientId' => env('GITHUB_CLIENT_ID'),
        'clientSecret' => env('GITHUB_CLIENT_SECRET'),
        'redirectUri' => env('APP_URL').'/auth/github/callback',
    ]),
),
```

league's GitHub provider reads the profile from `/user` only : `email` is the address the user made
public, and `null` when they keep it private.

## Testing

`FakeIdentityProvider` signs in the identity you give it, without any network. Put it in the
`SingleSignOn` component under the name of the provider it replaces, call your redirect route,
then your callback route with `callbackParameters()`

```php
class SignInTest extends CubeTestCase
{
    public function testAGoogleUserIsSignedIn()
    {
        $google = (new FakeIdentityProvider('google'))->withIdentity('10248', 'alice@example.com', name: 'Alice');

        SingleSignOn::withInstance(new SingleSignOn(new SingleSignOnConfiguration($google)), function () use ($google) {
            $this->get('/auth/google')->assertFound();
            $this->get('/auth/google/callback', $google->callbackParameters())->assertFound();
        });

        $this->assertModelExists(UserIdentity::class, ['provider' => 'google', 'external_id' => '10248']);
    }
}
```

| Method of `FakeIdentityProvider` | Does |
|---|---|
| `new FakeIdentityProvider($name = 'fake')` | a provider refusing every code, like one given an expired code |
| `withIdentity($id, $email, $emailVerified = true, $name, $raw)` | makes it sign in that identity |
| `callbackParameters()` | the `code` and `state` the provider would send back after the last redirect |

To test a provider of your own that extends `AuthorizationCodeProvider`, fake the provider's
server instead. Every request goes through `IdentityProviderClient`, so register an
`HttpMockServer` for it (see [Http client](./28-http-client.md#testing-without-the-network))

```php
MockServers::getInstance()->set(IdentityProviderClient::class, FakeDiscordServer::class);
```

The mock server routes on the path only, so one server answers for every host the provider uses.

## Reference

| Option of `SingleSignOnConfiguration` | Default | Does |
|---|---|---|
| `...$providers` | none | the `IdentityProvider` instances users can sign in with |

| Method of `SingleSignOn` | Does |
|---|---|
| `redirect(IdentityProvider\|string $provider)` | stores a pending authorization in the session and returns a `302` to the provider |
| `callback(Request $request, IdentityProvider\|string $provider)` | checks the `state`, exchanges the `code`, returns the `ExternalIdentity` or `null` |
| `provider(string $name)` | the configured provider with that name, or `null` |

| Method of `IdentityProvider` | Returns |
|---|---|
| `getName()` | the provider name |
| `authorize()` | a `PendingAuthorization` : the URL to send the user to, the `state` and the PKCE `codeVerifier` |
| `identify(string $code, PendingAuthorization $pending)` | the `ExternalIdentity`, or `null` when the provider refuses the code |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./25-remember-me.md">Previous : Remember me</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./27-encryption-and-signing.md">Next : Encryption and signing</a></div></td></tr></table>
