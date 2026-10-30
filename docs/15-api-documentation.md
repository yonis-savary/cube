<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./14-views-and-assets.md">Previous : Views and assets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./16-database-and-queries.md">Next : Database and queries</a></div></td></tr></table>

# Api documentation

Cube writes the OpenAPI 3.1 document of your API from your routes : `OpenAPIGenerator` reads your
controllers, their typed requests, slugs and return types, so most of the document needs no extra
work.

```bash
php do make:openapi
```

The document lands in `Storage/openapi.json`. Give it a title and a version in `cube.php`

```php
new OpenAPIConfiguration(
    outputFile: 'public/openapi.json', // relative to your project root
    title: 'Shop API',
    version: '1.2.0',
),
```

## What Cube reads from your code

Here is a controller documented without a single attribute

```php
class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post('/products', [self::class, 'store']),
            Route::get('/products/{product}', [self::class, 'show']),
        );
    }

    // body from StoreProductRequest rules, 422 when they fail, 200 with the Product schema
    public function store(StoreProductRequest $request): Product
    {
        return Product::fromArray($request->validated())->save();
    }

    // integer path parameter, 404 when no product matches, 200 with the Product schema
    public function show(Request $request, Product $product): Product
    {
        return $product;
    }
}
```

Only routes with an array callback are documented : a closure route is skipped, so prefer
`[ProductController::class, 'show']`. A route declared for any method (`Route::any()`) names no
method to document : it is skipped too, with a warning in the generation logs.

| Your code | The document |
|---|---|
| a slug `{product}` | a path parameter, typed from the callback parameter at the same position (the first one after the request) — a model gives the type of its primary key |
| a typed slug `{int:id}` | a path parameter of that type (`int`, `float`, `date`, `time`, `datetime`, `hex`, `uuid`, or your regex as a `pattern`) |
| a `Request` subclass with `getRules()`, on `GET` / `DELETE` | one query parameter per rule |
| a `Request` subclass with `getRules()`, on `POST` / `PUT` / `PATCH` | a required JSON request body |
| `#[RawBody]`, on `POST` / `PUT` / `PATCH` | a required binary request body, see [Receiving a raw body](#receiving-a-raw-body) |
| the same `Request` subclass | a `422` response, its body described by the shared `ValidationErrors` schema |
| a slug received as a model (`Product $product`) | a `404` response |
| a model return type (`: Product`) | a `200` response with the `Product` schema |
| a nullable model return type (`: ?Product`) | a `200` response, plus a `204` one for `null` |
| a `: void` return type | a `204` response |

From the rules, a `nullable` rule becomes a nullable type, a rule that is not `optional()` a required
key or parameter, `inArray()` an `enum` of the rule's type and `isBetween()` a `minimum` and a
`maximum` — on a number only, OpenAPI has no bound for a date or a string. A datetime is a string
with the `YYYY-MM-DD HH:MM:SS` `pattern` Cube reads and answers, not the RFC 3339 `date-time` format.
A rule without a type (`new Param()`) gives the empty schema `{}`, which accepts any value.

Every model a response refers to is described once in `components.schemas`, from
`Model::toResponseObjectParam()`, with an example made by its factory (see
[Testing](./33-testing.md#faking-models)) : that is what a model answered as is gives. A model read
from the database holds all its columns, so every field is a required property, the primary key
included, and a nullable column is typed `[type, null]`. Each relation is an optional property
pointing to the schema of its model, since a relation only appears once loaded

| Relation | Property, named after the relation |
|---|---|
| `HasOne` (`_type`) | `oneOf: [#/components/schemas/UserType, null]` — `null` when no row matches |
| `HasMany` (`modules`) | a list of `#/components/schemas/ModuleUser` |

A model only reached through a relation gets its schema too. A route
answering through a `ModelResource` declares it with `#[ResourceResponse]`, described below.
Those examples need `fakerphp/faker`, which Cube only suggests : install it in your development
dependencies (`composer require --dev fakerphp/faker`) to generate a document describing models.

Rules are read from a request built empty : when `getRules()` depends on the input, the document
shows the shape it returns for an empty request. A `getRules()` returning a single rule rather than
an array of rules describes the whole body (a list, for instance). On a `GET` / `DELETE`, only an
object rule gives query parameters : any other gives none, with a warning in the generation logs.

## Describing an endpoint

By default an operation is summarized by its method name, and described as `Controller.method`.
`#[Endpoint]` gives it a proper summary and description

```php
#[Endpoint('List products', 'Every product of the catalog, filtered by name')]
public function read(SearchProductRequest $request): array
```

The `operationId` is the camelCase of the summary (`listProducts`), or of the method name without
`#[Endpoint]`. OpenAPI wants it unique in the whole document, so the generation stops with a
`RuntimeException` naming both operations when two collide. Give them distinct methods, or set
`#[Endpoint(operationId: ...)]` yourself. A callback shared by several verbs
(`new Route('/', $callback, ['PUT', 'PATCH'])`) always collides : declare one route per verb.

## Leaving a route out

`#[Hidden]` on a controller method, or on the controller class, keeps its routes out of the document

```php
#[Hidden]
public function healthCheck(): Response
{
    return Response::ok();
}
```

## Receiving a raw body

A route reading the raw body (an uploaded chunk, an image) declares it with `#[RawBody]` : the
request body becomes a binary string of the given content type, `application/octet-stream` by
default

```php
#[RawBody('image/png', description: 'The new cover')]
public function uploadCover(Request $request, Album $album): Response
```

It replaces the JSON body the request rules would give.

## Describing a response

A response Cube cannot read from your return type is declared with an attribute. An attribute wins
over what the return type says for the same status code.

`#[ModelResponse]` answers a model, or a list of models with `isArray`

```php
#[ModelResponse(Product::class, isArray: true)]
public function read(Request $request): array

#[ModelResponse(Product::class, responseCode: StatusCode::CREATED, description: 'The created product')]
public function store(StoreProductRequest $request): Response
```

`with` names the relations the endpoint loads, dot-separated for nested ones. The model schema
leaves every relation optional, so the response adds what this endpoint always gives, without a
second copy of the schema

```php
#[ModelResponse(Order::class, with: ['customer', 'lines.product'])]
public function show(Order $order): Order
{
    return $order->load('customer', 'lines.product');
}
// schema : allOf [ #/components/schemas/Order, { required: [customer, lines], properties: { lines: { items: { required: [product] } } } } ]
```

`#[RawResponse]` describes any other shape from an example value, or from a JSON file

```php
#[RawResponse(['total' => 120, 'pages' => [1, 2, 3]])]
public function stats(): array

#[RawResponse(file: __DIR__.'/Examples/stats.json')]
public function detailedStats(): array
```

The example is only read for its shape : `120` becomes an integer, `[1, 2, 3]` a list of integers
(read from its first item), an associative array an object, `null` any value (`{}`), and any string
a string, even a model's class name : to reference a model's schema, use `#[ObjectResponse]`. Give
`file` relative to your project root, or as an absolute path inside it as `__DIR__` does : the
attribute checks the file exists before anything else.

With a content type other than JSON, `#[RawResponse]` needs no example : the response is a binary
string

```php
#[RawResponse(mimeType: 'audio/mpeg')]
public function stream(Track $track): Response
```

`#[NoContentResponse]` adds a `204`, next to the other responses of the endpoint

```php
#[ModelResponse(Album::class)]
#[NoContentResponse('The album has no cover')]
public function cover(Album $album): Response
```

`#[ResourceResponse]` answers what a `ModelResource` gives (see
[Controllers and responses](./13-controllers-and-responses.md#shaping-what-a-model-answers)) : Cube
makes a model with its factory, passes it to the resource, and reads the shape of the result, which
also becomes the response's example

```php
#[ResourceResponse(UserProfileResource::class, isArray: true)]
public function profiles(): array
```

Two endpoints can answer the same resource with different relations loaded : `with` names the ones
this endpoint loads, and the factory makes exactly those on the example model (see
[Testing](./33-testing.md#faking-relations))

```php
#[ResourceResponse(OrderResource::class, with: ['customer', 'lines.product'])]
public function show(Order $order): array
{
    return OrderResource::of($order->load('customer', 'lines.product'));
}
```

The resource reads the relations the endpoint loaded (`$model->customer`), it does not call `load()`
itself : on the example, `load()` would query an empty database and replace the made-up relation
with `null`. A resource reading a relation missing from `with` stops the generation with a
`RuntimeException`, since the model has no such value.

`#[ObjectResponse]` describes a route answering several models or resources at once, a page for
instance. Its schema is an example value read like `#[RawResponse]`'s, except that a `ModelResource`
class stands for what the resource answers, and a `Model` class for a `$ref` to the model's schema

```php
#[ObjectResponse([
    'album' => AlbumResource::class,     // the shape AlbumResource answers
    'tracks' => [TrackResource::class],  // a list of what TrackResource answers
    'label' => Label::class,             // #/components/schemas/Label
    'total' => 0,                        // an integer
])]
public function page(Request $request, Album $album): array
```

Only the document reads it : the route answers whatever it returns. Each resource is applied to a
model its factory made, the way `#[ResourceResponse]` does. A bare resource class gets a model
without relations : when the resource reads some, wrap it in a `ResourceSchema` naming the relations
the endpoint loads, dot-separated for nested ones

```php
#[ObjectResponse([
    'album' => new ResourceSchema(AlbumResource::class, with: ['label', 'tracks.artist']),
    'related' => [AlbumResource::class],  // no relation read : the class alone is enough
])]
public function page(Request $request, Album $album): array
{
    return [
        'album' => AlbumResource::of($album->load('label', 'tracks.artist')),
        'related' => AlbumResource::of(Album::select()->whereAssoc(['label' => $album->label])->fetchBunch()),
    ];
}
```

A `ResourceSchema` goes anywhere a resource class does, in a list too
(`[new ResourceSchema(TrackResource::class, with: ['artist'])]`). A resource reading a relation its
`with` does not name stops the generation with a `RuntimeException`, since the example model has no
such value.

`#[ModelResponse]`, `#[ResourceResponse]`, `#[ObjectResponse]` and `#[RawResponse]` take the same
optional arguments

| Argument | Default | Does |
|---|---|---|
| `responseCode` | `200` | the status code described |
| `description` | none | the response's description |
| `mimeType` | `application/json` | the content type described |

`#[ModelResponse]` and `#[ResourceResponse]` take a `with` list of relation names too, empty by
default.

`#[ModelResponse]`, `#[ResourceResponse]`, `#[ObjectResponse]` and `#[RawResponse]` can be repeated
on a method, one per status code : two attributes describing the same code keep the last one read.

## Authentication

Declare how your API authenticates and every operation of the document requires it

```php
new OpenAPIConfiguration(
    authenticationScheme: new BearerToken(), // Authorization: Bearer <JWT>
),
```

| Scheme | Documents |
|---|---|
| `new BearerToken(format: 'JWT')` | an `Authorization: Bearer` header, `format` being informative |
| `new ApiToken(headerName: 'X-API-Token')` | an API key sent in the given header |

Both take a `componentNameInDoc` too (`BearerAuth`, `ApiTokenAuth`), the name of the scheme in
`components.securitySchemes`.

The scheme applies to the whole document : a public route still shows as protected. The `401` or
`403` your middleware answers are not documented either, as Cube cannot know them.

## Generating from code

`OpenAPIGenerator` is a component : `generate()` writes the document and returns its path, the way
`make:openapi` does

```php
$path = OpenAPIGenerator::getInstance()->generate();
```

It takes the `Router` to read as an optional argument, the global one by default.

## Configuration

| `OpenAPIConfiguration` option | Default | Does |
|---|---|---|
| `outputFile` | `openapi.json` in your `Storage` | where the document is written, relative to your project root |
| `title` | `Application` | `info.title` |
| `version` | `0.0.1` | `info.version` |
| `authenticationScheme` | `null` | the scheme every operation requires, see above |
| `jsonFlags` | `JSON_PRETTY_PRINT \| JSON_THROW_ON_ERROR` | flags given to `json_encode()` |
| `displayLogs` | `true` | print every route and parameter while generating |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./14-views-and-assets.md">Previous : Views and assets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./16-database-and-queries.md">Next : Database and queries</a></div></td></tr></table>
