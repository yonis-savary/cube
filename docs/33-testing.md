<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./32-static-server.md">Previous : Static server</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./34-deployment.md">Next : Deployment</a></div></td></tr></table>

# Testing

Your project ships with a PHPUnit setup : `Cube\Test\CubeTestCase` drives your application through
its router, in-process, with a fresh database for every test, and returns a `ResponseAssert` to check
what came back.

Here is a test of a controller, in `Tests/Integration/ProductTest.php`

```php
namespace Tests\Integration;

class ProductTest extends CubeTestCase
{
    public function testStoreProduct()
    {
        $this->post('/product')->assertUnprocessableContent();

        $product = $this->postJson('/product', ['name' => 'Painting', 'price_dollar' => 120])
            ->assertOk()
            ->json();

        $this->assertEquals('Painting', $product['name']);

        $this->get('/product/'.$product['id'])
            ->assertOk()
            ->assertJsonContent($product);
    }
}
```

```sh
php do test
```

## What your project gets

The server files you copied at installation (see [Installation](./02-installation.md#the-server-files))
hold the whole setup

| File | Role |
|---|---|
| `phpunit.xml` | one suite, the `Tests/` directory ; stops on the first failure, fails on warnings and risky tests |
| `Tests/bootstrap.php` | boots Cube, moves `Storage` to a temporary directory and prepares the test database |
| `Tests/Unit/HelloWorldTest.php` | a placeholder test, delete it once you have your own |

Cube does not install PHPUnit for you, nor Faker, which [model factories](#faking-models) need. Your
tests also need composer's `Tests\` mapping (see [Installation](./02-installation.md))

```sh
composer require --dev phpunit/phpunit fakerphp/faker
```

`Tests/bootstrap.php` runs once per PHPUnit process, before any test

1. `Autoloader::initialize()` on your project, which reads your `cube.php` and your `.env` ;
2. replaces the `Storage` component with a new `Storage/Tests-<unique id>` directory
   (`TestContext::useTemporaryStorage()`) : logs, cache, test databases and files written during the
   run land there, never in your real storage, and the directory is removed when the process ends ;
3. builds an empty SQLite database, runs every migration of your applications into it, and makes it
   the global `Database`.

## Running the tests

`php do test` (or its full identifier `php do cube:test`) runs `vendor/bin/phpunit` from your project
and prints its output. It prints `PHPUnit bin not found` and answers `1` when PHPUnit is not installed.

Every argument goes to PHPUnit, the command answers PHPUnit's exit code, and it never times out

```sh
php do test
php do test --filter ProductTest
```

## A fresh database per test

The bootstrap migrates the SQLite database once. Before each test, `CubeTestCase::setUp()` copies that
migrated file and makes the copy the global `Database` : every test starts from your empty schema,
whatever the previous one wrote. A test of yours overriding `setUp()` calls `parent::setUp()` first.

Keep in mind

- The test database is **always SQLite**, whatever your `DatabaseConfiguration` says : your
  migrations must run on SQLite. Migrations written with the `Plan` API do ; a
  [raw SQL migration](./17-migrations.md#raw-sql-migrations) must use SQL that SQLite understands.
- Only the database is reset. Any other component you set in a test stays set for the next tests of
  the same process : put it back in `tearDown()` (see
  [Swapping a component](#swapping-a-component)).

The current test database is `$this->database`, also returned by `Database::getInstance()`. To check
a row from a test, assert it through its model

```php
$this->assertModelExists(Product::class, ['name' => 'Painting']);
```

## Sending requests

Each helper builds a `Request` and hands it to your application's `Router`, in the same process :
no web server, no network. Routing, middlewares, validation and model binding run as in production.

```php
$this->get('/product', ['name' => 'Glass']);                  // query string parameters
$this->post('/product', ['name' => 'Glass', 'price_dollar' => 5]);   // form parameters
$this->postJson('/product', ['name' => 'Glass', 'price_dollar' => 5]); // a JSON body
$this->delete('/product/12', headers: ['Authorization' => 'Bearer '.$token]);
```

| Method | Sends |
|---|---|
| `get($path, $getParams = [], $headers = [])` | a `GET` with query parameters |
| `post($path, $postParams = [], $getParams = [], $uploads = [], $headers = [])` | a `POST` with form parameters and uploaded files |
| `put($path, $getParams = [], $postParams = [], $headers = [])` | a `PUT` |
| `patch($path, $getParams = [], $postParams = [], $headers = [])` | a `PATCH` |
| `delete($path, $getParams = [], $postParams = [], $headers = [])` | a `DELETE` |
| `getJson`, `postJson`, `putJson`, `patchJson`, `deleteJson` `($path, $body = [], $headers = [])` | `$body` encoded as JSON, with a `content-type: application/json` header |

Watch the order : `post()` takes its form parameters first, `put()`, `patch()` and `delete()` take
the query parameters first. Named arguments avoid the trap

```php
$this->put('/product/12', postParams: ['name' => 'Screen']);
```

An exception your code does not catch is not turned into a `500` : it reaches PHPUnit and the test
errors with its trace. A `ResponseException` is unwrapped into its response, as in production.

### Uploading files

`makeFakeUpload()` and `makeFakeUploadFromContent()` write a temporary file and wrap it in an
`Upload`, ready for `post()`. The first argument is the input name

```php
$document = $this->makeFakeUploadFromContent('document', json_encode(['Hello' => 'Goodbye']));
$this->post('/documents', uploads: [$document])->assertCreated();

// 2 MB of zeros, to test a size limit
$tooBig = $this->makeFakeUpload('document', 2 * File::MEGABYTES, 'invoice.pdf', 'application/pdf');
$this->post('/documents', uploads: [$tooBig])->assertUnprocessableContent();
```

| Method | Builds |
|---|---|
| `makeFakeUpload($inputName, $size, $basename = null, $mime = 'text/plain', $error = UPLOAD_ERR_OK)` | a file of `$size` bytes |
| `makeFakeUploadFromContent($inputName, $content, $basename = null, $mime = 'text/plain', $error = UPLOAD_ERR_OK)` | a file holding `$content` |

Give another `$error` (`UPLOAD_ERR_INI_SIZE`...) to test how you handle a failed upload.

## Asserting on the response

Every helper returns a `ResponseAssert`. Its assertions return it again, so they chain, and end with
`json()` or `body()` when you need the content

```php
$products = $this->get('/product')
    ->assertOk()
    ->assertIsJson()
    ->assertHeaderEqual('X-Content-Type-Options', 'nosniff')
    ->json();
```

| Method | Does |
|---|---|
| `body()` | the raw body, as a string |
| `json()` | the body decoded as JSON, objects as associative arrays (`null` when it is not JSON) |
| `assertJsonContent($expected)` | asserts the decoded body equals `$expected` |
| `assertIsJson()` | asserts the response is JSON |
| `assertHeaderEqual($header, $expected)` | asserts a header's value |
| `assertResponseCode($code)` | asserts the status code |

When `assertResponseCode()` fails, the actual code, the headers and the body are written to the
`Logger` : read them there to see what the application answered.

Every status code has its shortcut, named after it

| Family | Shortcuts |
|---|---|
| `1xx` | `assertContinue`, `assertSwitchingProtocols`, `assertProcessing`, `assertEarlyHints` |
| `2xx` | `assertOk`, `assertCreated`, `assertAccepted`, `assertNonAuthoritativeInformation`, `assertNoContent`, `assertResetContent`, `assertPartialContent` (206), `assertMultiStatus`, `assertAlreadyReported`, `assertImUsed` |
| `3xx` | `assertMultipleChoices`, `assertMovedPermanently`, `assertFound`, `assertSeeOther`, `assertNotModified`, `assertUseProxy`, `assertUnused`, `assertTemporaryRedirect`, `assertPermanentRedirect` |
| `4xx` | `assertBadRequest`, `assertUnauthorized`, `assertPaymentRequired`, `assertForbidden`, `assertNotFound`, `assertMethodNotAllowed`, `assertNotAcceptable`, `assertProxyAuthenticationRequired`, `assertRequestTimeout`, `assertConflict`, `assertGone`, `assertLengthRequired`, `assertPreconditionFailed`, `assertContentTooLarge`, `assertURITooLong`, `assertUnsupportedMediaType`, `assertRangeNotSatisfiable`, `assertExpectationFailed`, `assertImATeapot`, `assertMisdirectedRequest`, `assertUnprocessableContent`, `assertLocked`, `assertFailedDependency`, `assertTooEarly`, `assertUpgradeRequired`, `assertPreconditionRequired`, `assertTooManyRequests`, `assertRequestHeaderFieldsTooLarge`, `assertUnavailableForLegalReasons` |
| `5xx` | `assertInternalServerError`, `assertNotImplemented`, `assertBadGateway`, `assertServiceUnavailable`, `assertGatewayTimeout`, `assertHttpVersionNotSupported`, `assertVariantAlsoNegotiates` (506), `assertInsufficientStorage`, `assertLoopDetected`, `assertNotExtended`, `assertNetworkAuthenticationRequired` |

The codes the router produces by itself are listed in
[Routing and middlewares](./11-routing-and-middlewares.md#responses-the-router-produces-on-its-own) :
a failed validation is `assertUnprocessableContent()`, a missing model `assertNotFound()`.

## Swapping a component

Rather than mocking, put another instance in place of the component your code reads (see
[Components](./06-components.md#replacing-the-instance)). Here is an example with an exchange rate
service, replaced by a fixed one

```php
class FixedExchangeRates extends ExchangeRates
{
    public function convert(float $amount, string $from, string $to): float
    {
        return $amount * 2;
    }
}

public function testCartTotalIsConverted()
{
    ExchangeRates::withInstance(new FixedExchangeRates(new HttpClient()), function () {
        $this->get('/cart', ['currency' => 'USD'])
            ->assertOk()
            ->assertJsonContent(['total' => 240]);
    });
}
```

Call `withInstance()` on the class your code asks for, `ExchangeRates` here :
`$fixed->asGlobalInstance(...)` would fill the `FixedExchangeRates` slot, which nobody reads. The
same goes for the logger : `Logger::withInstance($logger, ...)`.

`withInstance()` puts the previous instance back when the callback ends, even if it throws. With
`setInstance()` in `setUp()`, undo it yourself in `tearDown()`

```php
protected function setUp(): void
{
    parent::setUp();
    ExchangeRates::setInstance(new FixedExchangeRates(new HttpClient()));
}

protected function tearDown(): void
{
    ExchangeRates::removeInstance();
}
```

A class receiving the component in its constructor gets the instance in place when it is built
(see [Dependency injection](./05-dependency-injection.md)) : swap first, then send the request.

## Faking a remote service

A connector extending `HttpClient` answers from a `HttpMockServer` instead of the network : attach
the mock to the connector, or register it on the `MockServers` component when the connector is built
by your application

```php
MockServers::getInstance()->set(BillingConnector::class, BillingMocker::class);

$this->post('/orders/12/invoice')->assertCreated();
```

`CubeTestCase::tearDown()` removes every mock registered on `MockServers`, so the next test talks to
the network again unless it registers its own. A test of yours overriding `tearDown()` calls
`parent::tearDown()`.

The mock declares real routes and returns real responses. See
[Http client](./27-http-client.md#testing-without-the-network) for writing one.

## Tests without the application

Code that needs neither the router nor the database is tested with a plain
`PHPUnit\Framework\TestCase`, which is faster and resets nothing

```php
class PriceFormatterTest extends TestCase
{
    public function testFormatsCents()
    {
        $this->assertEquals('12.50 €', (new PriceFormatter())->format(1250));
    }
}
```

## The test context

`TestContext` is the component holding what the bootstrap prepared. You rarely need it directly

| Method | Gives |
|---|---|
| `getDatabase()` | the database of the current test |
| `getRouter()` | the router requests are sent to |
| `getStorage()` | the storage captured when the context was built |
| `getConfiguration()` | the configuration in use |
| `getMigrationManager()` | the migration manager of the current database |
| `useTemporaryStorage(?Storage $parent = null)` | static : a new `Tests-<unique id>` child made the global `Storage`, removed when the process ends (the bootstrap calls it) |
| `createEmptyApplicationDatabase()` | a new SQLite database with every migration applied (the bootstrap calls it) |
| `useNewEmptyApplicationDatabase()` | a fresh copy of that database, made global (`CubeTestCase::setUp()` calls it) |

## Faking models

`Model::factory()` builds models filled with fake values, from [Faker](https://fakerphp.org) : in your
tests, a seeder, or wherever an example is needed. Cube only suggests Faker : install it with
`composer require --dev fakerphp/faker`, otherwise a factory throws a `RuntimeException` saying so

```php
$user = User::factory()->make();                          // not saved
$user = User::factory()->create();                        // saved
$user = User::factory()->create(['login' => 'alice']);    // what you give wins
$members = AgencyUser::factory()->createMany(10, ['agency_id' => $agency->id()]);
$user = User::factory()->withSeed(42)->make();            // same seed, same values
```

| Method | Gives |
|---|---|
| `make(array $overrides = [])` | one model, not saved |
| `create(array $overrides = [], ?Database $database = null)` | one saved model |
| `makeMany(int $count, array $overrides = [])` | a list of models, not saved |
| `createMany(int $count, array $overrides = [], ?Database $database = null)` | a list of saved models |
| `withSeed(int $seed)` | the same factory, seeded : the same seed gives the same values |
| `with(string ...$relations)` | a copy of the factory that also makes these relations (see [Faking relations](#faking-relations)) |

### What each field gets

Every field you do not give gets a value of its type

| Field type | Fake value |
|---|---|
| `INTEGER` | between 1 and 1000 |
| `FLOAT` | between 0 and 1000, two decimals |
| `DECIMAL` | a string fitting the field's precision and scale (10 digits, 2 decimals when none) |
| `BOOLEAN` | `true` or `false` |
| `DATE` | a `Y-m-d` date between 2000 and 2030 |
| `DATETIME`, `TIMESTAMP` | a `Y-m-d H:i:s` date between 2000 and 2030 |
| `STRING` and anything else | a few words, cut to the field's `maximumLength` (50 characters when none) |

- A **unique** field gets a value the factory has not given yet.
- A field **referencing another model** gets, with `create()`, a row created first through that
  model's own factory, so the foreign key holds — a nullable reference included. With `make()`, it
  gets a made-up number and nothing is created.
- An **auto-incremented** or **generated** field, and a primary key with a database default, are left
  to the database by `create()`, and made up by `make()`, since an example still needs one.

### Faking relations

`with()` names the relations to make along with the model, dot-separated for nested ones. Each
related model comes from its own model's factory, with the keys tying it to its parent

```php
$user = User::factory()->with('_type')->make();               // $user->_type, $user->type === $user->_type->id
$order = Order::factory()->with('customer', 'lines.product')->create();
```

| Relation | Made |
|---|---|
| a `HasOne` to the key of its model (`_type` : `user.type` → `user_type.id`) | first, and its key copied into the parent's field |
| any other `HasOne`, or a `HasMany` | after the parent, with the parent's key — one model for a `HasMany` |

With `create()`, every related model is saved : a referenced row comes from the relation, not from
a second row created for the foreign key. A name missing from the model's `relations()` throws an
`InvalidArgumentException`.

`with()` gives a copy : unlike `withSeed()`, the factory `Model::factory()` shares keeps making bare
models. On a seeded factory, each relation gets a seed derived from it and from the related model,
so the whole tree is the same from one run to the next.

### A factory of your own

A factory of your own only gives the fields a type alone cannot fake well : an email, a password, a
price. Extend `ModelFactory`, name the model in `getModelClass()` and return those fields from
`definition()`

```php
/**
 * @extends ModelFactory<User>
 */
class UserFactory extends ModelFactory
{
    public static function getModelClass(): string
    {
        return User::class;
    }

    protected function definition(Generator $faker): array
    {
        return [
            'login' => $faker->unique()->email(),
            'password' => password_hash('secret', PASSWORD_DEFAULT),
        ];
    }
}
```

The factory is found by class discovery : put it in one of your applications (`App/Factories/`
for example), not in `Tests/`, which Cube does not explore (see
[Applications and discovery](./04-applications-and-discovery.md#class-discovery)). A model without a
factory of its own gets a `GenericModelFactory`, which fakes every field from its type.

`Model::factory()` gives the same factory instance for a model during the whole process : a
`withSeed()` call keeps applying to the following calls.

### Factories in the API documentation

The OpenAPI generator uses your factories too : the example of each model schema, and the shape and
example of a `#[ResourceResponse]`, come from a model made with `Product::factory()->withSeed(...)->make()`,
with the relations the attribute names in `with`.
A factory giving realistic values gives a realistic documentation. See
[API documentation](./15-api-documentation.md#describing-a-response).

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./32-static-server.md">Previous : Static server</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./34-deployment.md">Next : Deployment</a></div></td></tr></table>
