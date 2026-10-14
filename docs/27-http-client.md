<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./26-encryption-and-signing.md">Previous : Encryption and signing</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./28-schedules.md">Next : Schedules</a></div></td></tr></table>

# Http client

`HttpClient` calls other services over cURL and gives you back the same `Response` object your own
controllers return. The quickest form builds a `Request` and fetches it

```php
$response = (new Request('GET', 'https://api.example.com/products'))->fetch();

$products = $response->getJSON();
```

The form worth writing for a real integration is a **connector** : one `HttpClient` subclass per
remote service, so the base URL, the credentials and the endpoints live in one place.

## A connector

Extend `HttpClient`, answer for the service, and expose its calls as methods. Here is an example,
reading its settings from a [configuration element](./03-configuration.md#your-own-settings)

```php
class BillingApiConfiguration extends ConfigurationElement
{
    public function __construct(
        public readonly string $url = 'https://billing.example.com',
        public readonly ?string $token = null,
    ) {}
}

// cube.php
new BillingApiConfiguration(env('BILLING_URL', 'https://billing.example.com'), env('BILLING_TOKEN')),
```

```php
class BillingConnector extends HttpClient
{
    public function baseURL(): ?string
    {
        return BillingApiConfiguration::resolve()->url;
    }

    public function baseHeaders(): array
    {
        return ['authorization' => 'Bearer '.BillingApiConfiguration::resolve()->token];
    }

    public function baseLogger(): Logger
    {
        return Logger::forFile('billing.csv');
    }

    public function invoice(int $id): array
    {
        return $this->get("/invoices/{$id}")->getJSON();
    }
}

$invoice = (new BillingConnector())->invoice(42);
```

Everything a connector answers for is a method you may override

| Method | Default | Applies to |
|---|---|---|
| `baseURL()` | `null` | prefixed to every relative path |
| `baseUnixSocket()` | `null` | the unix socket every request goes through ([Unix socket server](./31-unix-socket-server.md#calling-the-socket-from-php)) |
| `baseHeaders()` | `[]` | merged into every request's headers |
| `baseURLParameters()` | `[]` | merged into every query string |
| `basePostParameters()` | `[]` | merged into every body |
| `baseLogger()` | a `NullLogger` | where the exchange is logged — nothing is logged unless you override it |
| `baseUserAgent()` | a Firefox user agent | the `User-Agent` header, when no `$userAgent` is given |

Base headers and parameters often carry credentials : with a `baseURL()`, they are only sent to its
origin (scheme, host and port). An absolute URL to another origin is still fetched, without them.
Only `http` and `https` are fetched, so a URL taken from user input cannot read `file://` or talk to
another protocol.

## Sending a request

The verb methods build the request, send it through the connector and return the `Response`

```php
$client->get('/products', ['page' => 2]);
$client->post('/products', ['name' => 'screen']);
$client->put('/products/12', [], ['name' => 'screen']);
$client->patch('/products/12', [], ['name' => 'screen']);
$client->delete('/products/12');
```

Mind the argument order : `post()` takes the body first, the other verbs take the query string first

| Method | Arguments |
|---|---|
| `get()` | `$path, $getParams = [], $headers = []` |
| `post()` | `$path, $postParams = [], $getParams = [], $uploads = [], $headers = []` |
| `put()`, `patch()`, `delete()` | `$path, $getParams = [], $postParams = [], $headers = []` |

Their `...Json` counterparts (`getJson()`, `postJson()`, `putJson()`, `patchJson()`, `deleteJson()`)
set the `content-type` to `application/json` and encode the body, which is what an API usually wants

```php
$client->postJson('/products', ['name' => 'screen']);
$client->patchJson('/products/12', ['name' => 'blue screen'], ['x-request-id' => $requestId]);
```

### Fire and forget

Every verb method also exists as `...Async` — `getAsync()`, `postAsync()`, `postJsonAsync()` and so
on. They write the request on a raw socket and close it without waiting for the answer, so they
return a `bool` telling you the connection opened, not a `Response`. Use them for a notification you
do not need to read.

```php
$client->postJsonAsync('/webhooks/order-shipped', ['order' => $order->id]);
```

The async form does not support uploads (it logs a warning and sends the rest) and never follows a
redirection, since it does not read the answer.

## Reading the response

The `Response` is the same class your controllers return, so the accessors are the ones you know

```php
$response = $client->get('/products');

$response->getStatusCode();      // 200
$response->isOk();               // true for any 2xx
$response->getBody();            // raw text
$response->getJSON();            // decoded, associative by default, throws a JsonException on invalid JSON
$response->getJSON(false);       // decoded as objects
$response->getHeader('ETag');    // header names are case-insensitive
```

When the payload has a shape you rely on, map it to a typed object rather than passing arrays around
(see [Data transfer objects](./20-data-transfer-objects.md#from-an-http-response))

```php
$invoice = $response->toObject(InvoiceData::class);
```

`toObject()` throws a `RuntimeException` when the response is not a `2xx` — it logs the headers and
the body first, so the failure is diagnosable.

`$client->lastDuration()` gives the duration of the connector's last `fetch()`, in milliseconds.

A transport failure (DNS, refused connection, timeout) throws a `RuntimeException` carrying the cURL
error. An HTTP error status does not throw : check `isOk()` or `getStatusCode()`.

## Tuning a single call

`Request::fetch()` takes the knobs, when the defaults do not fit

```php
$response = (new Request('GET', 'https://reports.example.com/monthly'))->fetch(
    timeout: 30,
    supportRedirection: false,
    logFlags: HttpClient::DEBUG_ALL,
);
```

| Argument | Default | Does |
|---|---|---|
| `$logger` | the connector's `baseLogger()` | where the exchange is logged |
| `$timeout` | none | cURL connect and total timeout, in seconds |
| `$userAgent` | the connector's `baseUserAgent()` | the `User-Agent` header, an empty string sends none |
| `$supportRedirection` | `true` | follow `301`, `302`, `303`, `307` and `308`, up to 10 times |
| `$logFlags` | `DEBUG_ESSENTIALS` | how much of the exchange is logged |
| `$httpClient` | a bare `HttpClient` | the connector to send through |
| `$curlMutator` | none | a callback receiving the cURL handle before it is executed |

`$curlMutator` is the escape hatch for anything Cube does not expose — a client certificate, a proxy,
a cURL option of your own

```php
$response = (new Request('GET', 'https://partner.example.com/stock'))->fetch(
    curlMutator: fn (CurlHandle $handle) => curl_setopt($handle, CURLOPT_SSLCERT, '/etc/ssl/partner.pem'),
);
```

A redirection is always followed with a `GET`. When it leads to another origin, it is sent through
a bare `HttpClient`, without the mutator and without the base headers and parameters, since they
often carry credentials. A redirection to anything but `http` or `https` is not followed : the
`3xx` response is returned as is.

### How much gets logged

The flags combine, so you log the half you care about

```php
$client->fetch($request, logFlags: HttpClient::DEBUG_REQUEST_HEADERS | HttpClient::DEBUG_RESPONSE_BODY);
```

| Flag | Logs |
|---|---|
| `DEBUG_REQUEST_CURL` | how the cURL handle is built : full URL, parameters and body, secrets included |
| `DEBUG_REQUEST_HEADERS` | request method, path and headers |
| `DEBUG_REQUEST_BODY` | request query string, post parameters and body |
| `DEBUG_REQUEST` | the three above |
| `DEBUG_RESPONSE_HEADERS` | response status, size and headers |
| `DEBUG_RESPONSE_BODY` | response body |
| `DEBUG_RESPONSE` | the two above |
| `DEBUG_ESSENTIALS` | request and response headers — the default |
| `DEBUG_ALL` | everything |

Header values whose name looks like a credential (`auth`, `cookie`, `token`, `secret`, `key`,
`password`, `session`) are logged as `[redacted]`. Bodies are not : think twice before turning
`DEBUG_ALL` on in production.

## Testing without the network

A `HttpMockServer` answers your connector's requests in-process, with real routes. Nothing goes out,
and you test your connector rather than a fixture. Declare its routes in `routes()`

```php
class BillingMocker extends HttpMockServer
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::get('/invoices/{int:id}', [self::class, 'invoice'])
        );
    }

    public static function invoice(Request $request, int $id): Response
    {
        return Response::json(['id' => $id, 'total' => 120]);
    }
}
```

Attach it to the connector for the length of a test

```php
$connector = new BillingConnector();
$connector->setMockServer(new BillingMocker());

$this->assertEquals(120, $connector->invoice(42)['total']);
```

The mock receives the path you asked for, without the `baseURL()` : `/invoices/42` here. An absolute
URL has its scheme and host stripped.

When the connector is built somewhere you do not control, register the pair on the `MockServers`
component instead. Every instance of that connector class then answers through the mock. It accepts
class names or instances, in any combination

```php
MockServers::getInstance()->set(BillingConnector::class, BillingMocker::class);

// the registration lives as long as the component : drop it when the test ends
MockServers::removeInstance();
```

An `...Async` call is answered by a mock too : the mock handles it, whichever way it was registered,
and the call returns `true`.

For a couple of routes, declaring a class is more than you need

```php
$server = HttpMockServer::fromArray([
    '/invoices/12' => Response::json(['id' => 12, 'total' => 120]),
    '/health' => fn () => Response::ok(),
]);
```

`fromArray()` answers those paths for any method. `fromRoutes(Route ...$routes)` is the same idea
when you want the full `Route` syntax — slugs, methods, middlewares.

See [Testing](./33-testing.md) for the rest of the test harness.

## Reference

| Constant | Value |
|---|---|
| `HttpClient::REDIRECTION_STATUSES` | `[301, 302, 303, 307, 308]` |
| `HttpClient::MAX_REDIRECTIONS` | `10` — a `RuntimeException` is thrown past it |
| `HttpClient::SENSITIVE_HEADERS` | the regex matching header names logged as `[redacted]` |

| Method | Returns |
|---|---|
| `Request::fetch(...)` | the `Response` |
| `Request::fetchAsync(?Logger $logger, ?string $userAgent, ?HttpClient $httpClient)` | `bool`, the connection opened |
| `HttpClient::fetch(Request $request, ...)` | the `Response`, same arguments as `Request::fetch()` |
| `HttpClient::toCurlHandle(Request $request, ...)` | the configured `CurlHandle`, without executing it |
| `HttpClient::lastDuration()` | duration of the last `fetch()`, in milliseconds |
| `HttpClient::setMockServer(HttpMockServer $mockServer)` | answers this instance's requests in-process |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./26-encryption-and-signing.md">Previous : Encryption and signing</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./28-schedules.md">Next : Schedules</a></div></td></tr></table>
