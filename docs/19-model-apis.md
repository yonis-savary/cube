<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./18-models.md">Previous : Models</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./20-data-transfer-objects.md">Next : Data transfer objects</a></div></td></tr></table>

# Model apis

`ModelAPI` does the CRUD work over one model : create, read with filters, update, delete. It
declares no route — your controller declares them, receives the `ModelAPI` by injection and
delegates to it.

Extend it once per model, or let `php do models:generate --apis` write these classes for every model
in `Http/Apis`

```php
/**
 * @extends ModelAPI<Product>
 */
class ProductModelAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }
}
```

Then wire it in a controller. Its constructor receives the `ModelAPI`, so its route methods are
instance methods

```php
class ProductController extends Controller
{
    public function __construct(
        protected ProductModelAPI $products
    ) {}

    public function routes(Router $router): void
    {
        $router->group('/product', routes: [
            Route::post('/', [self::class, 'create']),
            Route::get('/', [self::class, 'read']),
            Route::put('/{product}', [self::class, 'update']),
            Route::delete('/{product}', [self::class, 'delete']),
        ]);
    }

    public function create(WriteProductRequest $request): Response
    {
        return $this->products->createItems($request);
    }

    public function read(SearchProductRequest $request): array
    {
        return $this->products->readItems($request);
    }

    // The slug becomes the Product, Cube answers 404 when it does not exist
    public function update(WriteProductRequest $request, Product $product): array
    {
        return $this->products->updateItem($product, $request);
    }

    public function delete(Request $request, Product $product): Response
    {
        return $this->products->deleteItem($product);
    }
}
```

Since the routes are yours, so is everything around them : expose only the verbs you need, put them
behind a [middleware](./11-routing-and-middlewares.md), add
[OpenAPI attributes](./15-api-documentation.md) like on any other route.

## Declaring what a client may write and search

The request type decides what a client may write and search : a `Request` subclass writes and
filters on what its rules validate, nothing more — see
[Requests and validation](./12-requests-and-validation.md)

```php
class WriteProductRequest extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'name' => Param::string()->optional(),
            'price_dollar' => Param::float(true)->optional(),
        ];
    }
}

class SearchProductRequest extends Request
{
    public function getRules(): array|Rule
    {
        return [
            'id' => Param::integer()->optional(),
            'name' => Param::string()->optional(),
        ];
    }
}
```

A plain `Request` declares no rule, so it could reach any column — a `role`, an `owner_id`, a
password hash. `createItems()` and `updateItem()` throw an `InvalidArgumentException` when given one,
and `readItems()` filters on nothing with it. A subclass whose `getRules()` returns an empty array
counts as a plain `Request`.

## Creating one row or several

`createItems()` creates one row from a validated object, or one per element of a validated list,
and always answers `201` with the list of created rows. To accept both shapes on the same route,
let your request pick its rules from the body

```php
class WriteProductRequest extends Request
{
    public function getRules(): array|Rule
    {
        $product = [
            'name' => Param::string()->optional(),
            'price_dollar' => Param::float(true)->optional(),
        ];

        return Utils::isList($this->all())
            ? Param::array(Param::object($product))
            : $product;
    }
}
```

```
POST /product   {"name": "Painting", "price_dollar": 120}            ->  201 [{"id": 1, "name": "Painting", ...}]
POST /product   [{"name": "Table"}, {"name": "Chair"}]               ->  201 [{"id": 2, ...}, {"id": 3, ...}]
```

## Searching

A validated parameter matching a `STRING` field becomes a `LIKE` search, split on spaces, every
word having to match. Any other field type is compared for equality, and a parameter that is not a
field of the model is ignored.

```
GET /product?name=blue screen   ->  name LIKE '%blue%' AND name LIKE '%screen%'
GET /product?name=100%          ->  name LIKE '%100\%%'   (searched as written, not as a wildcard)
GET /product?id=12              ->  id = 12
```

Only the first 8 words are searched, so a long value cannot build an unbounded query : override
`getMaxSearchWords()` to change it. `readItems()` has no pagination, put a limit of your own on large
tables.

## Shaping the answer

Every row is answered whole, a password hash included. Override `getModelResource()` to answer
through a `ModelResource` of yours — see
[Controllers and responses](./13-controllers-and-responses.md#shaping-what-a-model-answers)

```php
class PublicProductAPI extends ModelAPI
{
    public function getModelClass(): string
    {
        return Product::class;
    }

    protected function getModelResource(): ?ModelResource
    {
        return new PublicProductResource();
    }
}
```

## Without a class of your own

When you have nothing to override, `forModel()` builds a `ModelAPI` without writing a class

```php
public function read(SearchProductRequest $request): array
{
    return ModelAPI::forModel(Product::class)->readItems($request);
}
```

Prefer the subclass as soon as you want to inject it : the `Injector` resolves a class by its type,
which an anonymous class does not have.

## Reference

| Method | Does | Answers |
|---|---|---|
| `createItems(Request)` | creates one row from the validated object, or one per element of a validated list | `201` with the list of created rows, `422` (plain text) when the validated value is not an array |
| `readItems(Request)` | reads rows, filtered by the validated fields | the matching rows |
| `updateItem(Model, Request)` | updates the given row with the validated fields, the primary key excepted | the updated row, read again |
| `deleteItem(Model)` | deletes the given row | `200` |

`updateItem()` and `deleteItem()` throw an `InvalidArgumentException` when the item is not an
instance of the class `getModelClass()` returns, or when that model has no primary key.

| Override | Default | Does |
|---|---|---|
| `getModelClass()` | — (abstract) | the model this API handles |
| `getModelResource()` | `null` | the resource every method answers with, `null` for the whole model |
| `getMaxSearchWords()` | `8` | how many words of a string filter are searched |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./18-models.md">Previous : Models</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./20-data-transfer-objects.md">Next : Data transfer objects</a></div></td></tr></table>
