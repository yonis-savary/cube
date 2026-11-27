<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./26-single-sign-on.md">Previous : Single sign on</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./28-encryption-and-signing.md">Next : Encryption and signing</a></div></td></tr></table>
# Rate limiting

A `RateLimiter` allows at most a number of attempts per window of time for each key : the requests
of a client, the logins tried on an account, the calls you make to a third-party API. The `Throttle`
middleware applies one to your routes and answers `429 Too Many Requests` past the limit.

Here is a policy of 60 requests per minute for each client, applied to a group of routes

```php
class ApiRateLimiter extends RequestRateLimiter
{
    protected function limit(): int
    {
        return 60;
    }

    protected function window(): int
    {
        return Cache::MINUTE;
    }
}

class ApiThrottle extends Throttle
{
    protected static function limiter(): RequestRateLimiter
    {
        return new ApiRateLimiter();
    }
}

$router->group('/api', [ApiThrottle::class], function (Router $router) {
    // ...
});
```

## How attempts are counted

Each policy is a class : `limit()` attempts per `window()` seconds, for each key. Cube counts them in
the `Cache` over a sliding window : at a given moment, the attempts of the previous window still
count for the part of it the last `window()` seconds cover. Ten seconds into a one-minute window, the
previous window weighs 50/60 of its attempts. A client therefore cannot send twice the limit around
the moment a window ends, as it could if the counter only restarted every minute.

A refused attempt counts nothing : a client that keeps retrying gets through as soon as its earlier
attempts weigh little enough.

Two different classes never share a counter, even with the same key. Two routes behind the same
policy share the budget of each client : give them two policies to count them apart.

## Limiting anything

`attempt()` counts one attempt for a key and tells you whether it was allowed. Extend `RateLimiter`
for a policy that has nothing to do with a request

```php
class ReportExportRateLimiter extends RateLimiter
{
    protected function limit(): int
    {
        return 5;
    }

    protected function window(): int
    {
        return Cache::HOUR;
    }
}

$rateLimit = (new ReportExportRateLimiter())->attempt("user-{$user->id}");
if (!$rateLimit->allowed)
    return Response::tooManyRequests("Retry in {$rateLimit->retryAfter} seconds");
```

An attempt can cost more than one unit, for an endpoint heavier than the others : `attempt($key, 10)`.
A cost greater than `limit()` can never be allowed, and throws an `InvalidArgumentException`.

`attempt()` returns a `RateLimit`

| Property | Holds |
|---|---|
| `allowed` | whether the attempt was allowed, and counted |
| `remaining` | the units still available now |
| `retryAfter` | seconds before the same attempt is allowed, `0` when it was |
| `resetsIn` | seconds before the current window ends |
| `policy`, `limit`, `window` | the name of the policy (`api-rate-limiter` for `ApiRateLimiter`) and its settings |

## Throttling your own calls to an API

`wait()` blocks until the attempt fits, then counts it : the way to stay under the limit an API puts on
you, in a queue job or a command

```php
class StripeRateLimiter extends RateLimiter
{
    protected function limit(): int
    {
        return 100;
    }

    protected function window(): int
    {
        return Cache::SECOND;
    }
}

$limiter = new StripeRateLimiter();
foreach ($invoices as $invoice) {
    $limiter->wait('charges');
    $stripe->charge($invoice);
}
```

`wait('charges', timeout: 30)` throws a `RateLimitTimeoutException` instead of waiting more than
30 seconds. It throws as soon as it knows the attempt cannot fit in time, without sleeping first.

The counter lives in the `Cache`, so every process sharing that cache shares the limit : several
queue workers calling the same API stay under it together.

## Limiting login attempts

To count failed logins only, check with `availableIn()`, which counts nothing, then count each
failure with `attempt()`

```php
class LoginRateLimiter extends RateLimiter
{
    protected function limit(): int
    {
        return 5;
    }

    protected function window(): int
    {
        return Cache::MINUTE * 15;
    }
}

public function login(LoginRequest $request): Response
{
    $limiter = new LoginRateLimiter();
    $email = $request->param('email');

    if ($limiter->availableIn($email) > 0)
        return Response::tooManyRequests('Too many failed attempts, retry later');

    if (!Authentication::getInstance()->attempt($email, $request->param('password'))) {
        $limiter->attempt($email);
        return Response::unauthorized();
    }

    $limiter->clear($email);
    return Response::noContent();
}
```

## Requests and the Throttle middleware

A `RequestRateLimiter` takes its key from the request with `keyOf()` : the client address by default.
Override it to count something else, such as the authenticated user

```php
class ApiRateLimiter extends RequestRateLimiter
{
    // limit() and window() as above

    protected function keyOf(Request $request): string
    {
        $authentication = Authentication::getInstance();

        return $authentication->isLogged()
            ? 'user-'.$authentication->userId()
            : $request->getClientIp() ?? 'no-address';
    }
}
```

`isLogged()` opens the session if no bearer token authenticated the request. On a stateless API,
run `BearerAuthentication` before the throttle and keep the session closed.

`$limiter->attemptRequest($request)` is `attempt()` with that key. A request without address, such as
one received by the [Unix socket server](./34-unix-socket-server.md), counts under one shared key.

A `Throttle` subclass gives its `limiter()` to each request it guards. Past the limit, it answers
`429 Too Many Requests` with a `Retry-After` header and the callback does not run. Under it, the
response gets the same rate limit headers, so a client can slow down before being refused

| Header | Example | Holds |
|---|---|---|
| `RateLimit-Policy` | `"api-rate-limiter";q=60;w=60` | the limit (`q`) per window of `w` seconds |
| `RateLimit` | `"api-rate-limiter";r=42;t=18` | the units remaining (`r`), and the seconds before the window ends, or before a retry when refused (`t`) |
| `Retry-After` | `12` | refused requests only : the seconds before retrying |

They follow the IETF draft *RateLimit header fields for HTTP*.

The `429` is documented in your OpenAPI document for every route behind a `Throttle`, without any
attribute to add : see [API documentation](./15-api-documentation.md).

## Behind a reverse proxy

Behind a reverse proxy or a load balancer, every request comes from the proxy's address, and every
client would share one counter. List your proxies in `trustedProxies` : `getClientIp()` then reads the
address they forward in `X-Forwarded-For`

```php
new RouterConfiguration(
    trustedProxies: ['10.0.0.0/8', 'fd00::/8'],
)
```

Cube reads `X-Forwarded-For` from the right and stops at the first address that is not a trusted
proxy : a client can write anything at the start of that header, but cannot forge what your own proxy
appended. Without `trustedProxies`, `getClientIp()` is `getIp()` and the header is ignored.

## Where the counters live

The counters are kept in `Cache::getInstance()`, through `Cache::increment()`, which every driver
performs atomically, even between processes. Override `cache()` in a policy to keep them in another
cache.

With a `LocalDiskCache`, only the processes of one server share the counters. Behind a load balancer,
use a `RedisCache` so that every server counts together : see
[Storage and caching](./21-storage-and-caching.md#drivers).

## Reference

| Method of `RateLimiter` | Does |
|---|---|
| `attempt(string $key, int $cost = 1): RateLimit` | counts the attempt when it fits, refuses it otherwise |
| `wait(string $key, int $cost = 1, ?float $timeout = null): RateLimit` | sleeps until the attempt fits, then counts it |
| `availableIn(string $key, int $cost = 1): float` | seconds before the attempt fits, `0` when it already does, counting nothing |
| `clear(string $key): void` | forgets the attempts of the key |
| `name(): string` | the policy name in the headers, the class name in kebab-case by default |

| To override | Required | Default |
|---|---|---|
| `limit(): int` | yes | |
| `window(): int` | yes | in seconds, `Cache::MINUTE` for instance |
| `cache(): Cache` | no | a `child()` of `Cache::getInstance()` named after the class |
| `keyOf(Request $request): string` (`RequestRateLimiter`) | no | `getClientIp()`, or `no-address` |
| `limiter(): RequestRateLimiter` (`Throttle`) | yes | |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./26-single-sign-on.md">Previous : Single sign on</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./28-encryption-and-signing.md">Next : Encryption and signing</a></div></td></tr></table>
