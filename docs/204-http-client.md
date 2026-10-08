<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./203-user-component.md">Previous : User component</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./205-schedule-and-queues.md">Next : Schedule and queues</a></div></td></tr></table>

# HTTP Client (curl)

`HttpClient` calls other services over cURL, and gives you back the same `Response` object your own
controllers return. The quickest form takes a `Request` and sends it

```php
$response = (new Request('GET', 'https://api.example.com/products'))->fetch();

$products = $response->getJSON();
```

But the form worth writing is a **connector** : one subclass per remote service, so the base URL, the
headers and the endpoints live in one place.

## A connector

Extend `HttpClient`, answer for the service, and expose the calls as methods

```php
class BillingConnector extends HttpClient
{
    public function baseURL(): ?string
    {
        return env('BILLING_URL', 'https://billing.example.com');
    }

    public function baseHeaders(): array
    {
        return ['authorization' => 'Bearer '.env('BILLING_TOKEN')];
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
| `baseURL()` | `null` | prefixed to every path |
| `baseUnixSocket()` | `null` | the unix socket every request goes through ([Unix socket server](./207-unix-socket-server.md#calling-the-socket-from-php)) |
| `baseHeaders()` | `[]` | merged into every request's headers |
| `baseUserAgent()` | a Firefox user agent | the `User-Agent` header |
| `baseURLParameters()` | `[]` | merged into every query string |
| `basePostParameters()` | `[]` | merged into every body |
| `baseLogger()` | the `Logger` component | where the exchange is logged |

## Sending a request

The verb methods build the request and return the `Response`

```php
$client->get('/products', ['page' => 2]);
$client->post('/products', ['name' => 'screen']);
$client->put('/products/12', [], ['name' => 'screen']);
$client->patch('/products/12', [], ['name' => 'screen']);
$client->delete('/products/12');
```

Their `...Json` counterparts set the content type and encode the body, which is what an API usually
wants

```php
$client->postJson('/products', ['name' => 'screen']);
$client->patchJson('/products/12', ['name' => 'blue screen']);
```

Every one of them also exists as `...Async` — `getAsync()`, `postJsonAsync()` and so on. Those return
a `bool` telling you the request went out, not a `Response` : use them to fire a notification you do
not need to read.

## Reading the response

The `Response` is the same class your controllers return, so the accessors are the ones you know

```php
$response = $client->get('/products');

$response->getStatusCode();      // 200
$response->getBody();            // raw text
$response->getJSON();            // decoded, associative by default
$response->getJSON(false);       // decoded as objects
$response->getHeader('etag');
```

When the payload has a shape you rely on, map it to a typed object rather than passing arrays around

```php
$invoice = $response->toObject(InvoiceData::class);
```

`toObject()` throws when the response is not a success — it logs the headers and the body first, so
the failure is diagnosable.

## Tuning a single call

`fetch()` takes the knobs, when the defaults do not fit

```php
$response = (new Request('GET', 'https://slow.example.com/report'))->fetch(
    timeout: 30,
    supportRedirection: false,
    logFlags: HttpClient::DEBUG_ALL,
);
```

| Argument | Default | Does |
|---|---|---|
| `$logger` | the `Logger` component | where the exchange is logged |
| `$timeout` | none | cURL timeout, in seconds |
| `$userAgent` | a Firefox user agent | the `User-Agent` header |
| `$supportRedirection` | `true` | follow `3xx` |
| `$logFlags` | `DEBUG_ESSENTIALS` | how much of the exchange is logged |
| `$httpClient` | a bare `HttpClient` | the connector to send through |
| `$curlMutator` | none | a callback receiving the cURL handle before it is executed |

`$curlMutator` is the escape hatch for anything Cube does not expose — a client certificate, a proxy,
a cURL option of your own.

### How much gets logged

The flags combine, so you log the half you care about

| Flag | Logs |
|---|---|
| `DEBUG_REQUEST_CURL` | the equivalent curl command line |
| `DEBUG_REQUEST_HEADERS` | request headers |
| `DEBUG_REQUEST_BODY` | request body |
| `DEBUG_REQUEST` | the three above |
| `DEBUG_RESPONSE_HEADERS` | response headers |
| `DEBUG_RESPONSE_BODY` | response body |
| `DEBUG_RESPONSE` | the two above |
| `DEBUG_ESSENTIALS` | request and response headers — the default |
| `DEBUG_ALL` | everything |

Remember that a body may hold credentials or personal data before turning `DEBUG_ALL` on in
production.

## Testing without the network

A `HttpMockServer` answers your connector's requests in-process, with real routes. Nothing goes out,
and you test your connector rather than a fixture.

```php
class BillingMocker extends HttpMockServer
{
    public function routes(Router $router)
    {
        $router->addRoutes(
            Route::get('/invoices/{int:id}', [self::class, 'invoice'])
        );
    }

    public static function invoice($request, int $id)
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

When the connector is built somewhere you do not control, register the pair on the `MockServers`
component instead — it accepts class names or instances, in any combination

```php
MockServers::getInstance()->set(BillingConnector::class, BillingMocker::class);
```

For a couple of routes, declaring a class is more than you need

```php
$server = HttpMockServer::fromArray([
    '/invoices/12' => Response::json(['id' => 12, 'total' => 120]),
    '/health' => fn () => Response::ok(),
]);
```

`fromRoutes(Route ...$routes)` is the same idea when you want the full `Route` syntax — slugs,
methods, middlewares.
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./203-user-component.md">Previous : User component</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./205-schedule-and-queues.md">Next : Schedule and queues</a></div></td></tr></table>
