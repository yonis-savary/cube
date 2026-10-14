<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./11-routing-and-middlewares.md">Previous : Routing and middlewares</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./13-controllers-and-responses.md">Next : Controllers and responses</a></div></td></tr></table>

# Requests and validation

`Request` holds what the client sent, normalized once at the edge. A `Request` subclass declares the
shape it expects with `Param` rules : the router validates it before your callback runs, so a
controller never re-checks its input.

Here is a request class and the callback receiving it

```php
class StoreProductRequest extends Request
{
    public function getRules(): array
    {
        return [
            'name' => Param::string(),
            'price_dollar' => Param::float(nullable: true)->optional(),
            'managers' => Param::array(Param::object(['email' => Param::email()])),
        ];
    }
}

class ProductController extends Controller
{
    public function routes(Router $router): void
    {
        $router->addRoutes(
            Route::post('/products', [self::class, 'store']),
        );
    }

    // Invalid input never reaches this body : Cube answers 422 with the errors
    public static function store(StoreProductRequest $request)
    {
        $name = $request->validated('name'); // a trimmed string
        $all = $request->validated();        // every validated, coerced value

        return Product::fromRequest($request)->save();
    }
}
```

The request is always the **first** parameter of a callback (see
[Callback parameters](./11-routing-and-middlewares.md#callback-parameters)).

## Reading the request

The request has already normalized the incoming data : in the query string and in a form body,
`"true"`/`"on"`, `"false"`/`"off"` and `"null"` (any case) are real `true`, `false` and `null`. A
JSON body (`Content-Type: application/json` or `application/*+json`) is decoded into the POST bag
as is.

```php
$name = $request->param('name');                   // one value, GET or POST
$name = $request->param('name', 'unnamed');        // with a fallback
[$page, $size] = $request->list(['page', 'size']); // several, positionally
$filters = $request->params(['name', 'price']);    // several, keyed
```

| Method | Gives you |
|---|---|
| `param(string $name, mixed $default = null)` | one value, GET first then POST |
| `params(array $keys, array $default = [])` | those keys, as an associative array (`$default` is keyed too) |
| `list(array $keys, array $default = [])` | those keys, as a positional list to destructure |
| `collect(string $key)` | one value wrapped in a `Bunch` |
| `all(bool $getParamsGotPriority = true)` | GET and POST merged, GET winning by default |
| `get()` / `post()` | one bag only |
| `only(array $keys, bool $validated = true)` | `validated()` restricted to those keys, or `all()` with `false` |
| `upload(string $inputName)` | a single `Upload`, `null` when absent |
| `uploads(string $inputName)` | every `Upload` sent under that name |
| `getUploads()` | every `Upload` of the request |
| `getHeader(string $name, ?string $default = null)` / `getHeaders()` | headers, names are case-insensitive |
| `getBody()` / `getJSON()` | the raw body, or the body decoded as JSON |
| `isJSON()` | whether the body is declared as JSON |
| `getMethod()` / `getPath()` / `getIp()` / `getCookies()` | the request line and its context |
| `getRoute()` / `getSlugValues()` / `getSlugObject(string $name)` | what the router matched (see [Routing](./11-routing-and-middlewares.md#route-parameters-slugs)) |

The request body is bounded by PHP and your web server, not by Cube : set `post_max_size`,
`upload_max_filesize` and nginx's `client_max_body_size` to what your routes need. For files
bigger than one request, see [Large uploads](./13-controllers-and-responses.md#large-uploads).

## Validating input

Declare the expected shape in the `getRules()` method of a `Request` subclass : an associative array
of rules, one per key. Type the first parameter of your callback with that subclass, and the router
validates the request **before** your callback runs.

When validation fails, Cube answers `422 Unprocessable Content` with the errors as JSON, keyed like
the input

```json
{
    "name": ["name cannot be null"],
    "price_dollar": ["price_dollar must be a float, got abc"],
    "managers": [
        { "email": ["email must be an email, got bad"] }
    ],
    "address": {
        "city": ["city is required"]
    }
}
```

A field answers a list of messages ; a nested object answers an object of its own fields ; a list
answers its failing items under their index.

### Reading the validated values

`validated()` gives the values after every rule ran : trimmed, coerced, defaulted. Keys without a
rule are left out.

```php
$request->validated();                // every validated value
$request->validated('name');          // one of them
$request->only(['name', 'managers']); // a subset
```

`validated('price_dollar')` answers `null` for an optional key that was not sent, and throws an
`InvalidArgumentException` for a key that has no rule at all. On a plain `Request`, which declares no
rule, `validated()` gives every value of the request.

### Required, optional and nullable

A key and its value are checked separately : a rule is **required** unless you call `optional()`,
and refuses `null` unless it is `nullable`

| Rule | Missing key | `null` value |
|---|---|---|
| `Param::string()` | refused, `name is required` | refused, `name cannot be null` |
| `Param::string(nullable: true)` | refused | accepted |
| `Param::string()->optional()` | accepted | refused |
| `Param::string(nullable: true)->optional()` | accepted | accepted |

A missing optional key is left out of `validated()`, so patching a model with
`$product->merge($request)` only touches the fields that were sent.

`default()` fills a missing key, or replaces a `null` value, before the other checks run

```php
'role' => Param::string()->default('customer'),
```

On a whole object, `withOptionalKeys()` and `withRequiredKeys()` switch several keys at once

```php
Product::toObjectParam()->withOptionalKeys(['name', 'price_dollar']);
```

Every factory takes its `nullable` flag as a boolean argument, and `string()` has a `trim` flag
before it : **use named arguments**, `Param::string(nullable: true)` reads where
`Param::string(true, true)` does not.

## Rules

`Param` composes the shape

| Factory | Accepts |
|---|---|
| `Param::string(bool $trim = true, bool $nullable = false)` | any scalar, trimmed when it is a string |
| `Param::integer(bool $nullable = false)` | an int, or a string writing a whole number (`"12"`, not `"1.9"` nor `" 5"`), coerced to `int` |
| `Param::float(bool $nullable = false)` | a number or a numeric string, coerced to `float` |
| `Param::boolean(bool $nullable = false)` | any scalar : `true`, `"on"`, `"true"`, `"yes"`, `"1"` give `true`, anything else `false` |
| `Param::email(bool $nullable = false)` | an email, checked by `filter_var()` |
| `Param::url(bool $nullable = false)` | a URL, the scheme being optional |
| `Param::uuid(bool $nullable = false)` | a UUID, any case |
| `Param::date(bool $nullable = false)` | a `YYYY-MM-DD` date that exists in the calendar |
| `Param::datetime(bool $nullable = false, bool $addTimeIfMissing = false)` | a `YYYY-MM-DD HH:MM:SS` date-time ; `addTimeIfMissing` appends `00:00:00` to a bare date |
| `Param::object(array $rules = [], bool $nullable = false)` | an associative array, each key checked by its rule |
| `Param::array(Rule\|array $childRule, bool $nullable = false)` | a list whose every item matches `$childRule` |
| `Param::anyKeyObject(Rule\|array $valueRule, bool $nullable = false)` | a map with unknown keys and a known value shape |
| `Param::model(string $modelClass, ?string $column = null, bool $nullable = false, array $with = [])` | a value that exists in `$column` (the primary key by default), **replaced by the model** |
| `Param::exists(string $modelClass, ?string $column = null, bool $nullable = false)` | a value that exists in `$column`, kept as is |
| `UploadRule::new(bool $nullable = false, bool $rejectUploadsWithErrors = true)` | an uploaded file, see [Uploads](#uploads) |

A plain array in place of a rule is read as `Param::object()` of that array

```php
return [
    'address' => [
        'street' => Param::string(),
        'city' => Param::string(),
    ],
];
```

### Refining a rule

These steps chain on any value rule

| Step | Checks |
|---|---|
| `inArray(array $values)` | the value is one of `$values` |
| `isBetween($min, $max, bool $canBeEqual = true)` | the value lies between the bounds |

And these on the composites

| Step | Does |
|---|---|
| `ArrayParam::minSize(int)` / `maxSize(int)` / `size(int)` | bounds the number of items |
| `ObjectParam::with(array $rules)` | adds or replaces rules |
| `ObjectParam::without(array\|string $keys)` | removes rules |
| `ObjectParam::withOptionalKeys(array\|string $keys)` / `withRequiredKeys(...)` | switches keys between optional and required |

```php
'quantity' => Param::integer()->isBetween(1, 100),
'status' => Param::string()->inArray(['draft', 'published']),
'tags' => Param::array(Param::string())->maxSize(10),
```

A model already describes its fields : `Product::toObjectParam()` gives the `ObjectParam` of every
field, which you can narrow with `without()` and `withOptionalKeys()` (see [Models](./18-models.md)).

### Your own checks and transformations

A rule is a pipeline of small steps. Add your own with `withCondition()` and `withTransformer()` —
on `object()` and `array()` too, where they receive the validated children

```php
Param::object(['password' => Param::string(), 'confirmation' => Param::string()])
    ->withCondition(fn (array $value) => $value['password'] === $value['confirmation'], 'passwords do not match');

Param::string()->withTransformer(fn ($value) => is_string($value) ? strtoupper($value) : $value);
```

| Method | Callback |
|---|---|
| `withCondition(callable $check, callable\|string $errorMessage)` | receives the value, `null` included ; the value passes only when it returns exactly `true` |
| `withValueCondition(callable $check, callable\|string $errorMessage)` | the same, but a `null` value always passes : use it for a format check on a nullable rule |
| `withTransformer(callable $transform)` | receives the value, `null` included, and returns the new value |

The error message may use `{key}` and `{value}`, or be a callable receiving the value and returning
the message

```php
Param::integer()->withCondition(fn ($value) => $value % 2 === 0, '{key} must be even, got {value}');
```

Steps run in the order you declared them and stop at the first failed check, so a condition never
sees a value an earlier check refused.

### A rule for the whole body

`getRules()` may also return a single `Rule` instead of an array, to accept a list for instance

```php
class WriteProductsRequest extends Request
{
    public function getRules(): array|Rule
    {
        $product = [
            'name' => Param::string()->optional(),
            'price_dollar' => Param::float(nullable: true)->optional(),
        ];

        return Utils::isList($this->all())
            ? Param::array(Param::object($product))
            : $product;
    }
}
```

### Validating by hand

When you need the errors rather than a `422`, take a plain `Request` and validate it yourself :
`validate()` returns a `ValidationReturn` holding both the errors keyed by path and the coerced result

```php
public static function import(Request $request)
{
    $result = WriteProductsRequest::fromRequest($request)->validate();

    if (!$result->isValid())
        return Response::json(['errors' => $result->getErrors()], StatusCode::BAD_REQUEST);

    $values = $result->getResult();
    // ...
}
```

`$request->isValid()` answers the question directly, and any rule validates any value on its own :
`Param::email()->validate($value, 'email')`.

## Uploads

Uploads are validated alongside the body : the rule key is the input name

```php
class StoreDocumentRequest extends Request
{
    public function getRules(): array
    {
        return [
            'to-upload' => UploadRule::new()
                ->withMimeType('application/json')
                ->withMaxSize(File::KILOBYTES * 5),
        ];
    }
}
```

`validated('to-upload')` then gives the `Upload`. Without `withMimeType()` and `withMaxSize()`, any
type and size is accepted. `withMimeType()` checks the type detected from the file's content, not the
one the client declared. An upload PHP reported as failed is refused with PHP's reason, unless you
pass `rejectUploadsWithErrors: false`.

An upload the client may leave out is `nullable` : `validated('avatar')` then gives `null`. An empty
file input counts as left out.

```php
'avatar' => UploadRule::new(nullable: true),
```

`UploadRule` checks a single file : several files sent under its name are refused. To accept several,
wrap it in `Param::array()` — the rule then gets a list of `Upload`, even when only one file was sent.

```php
'photos' => Param::array(UploadRule::new()->withMaxSize(File::MEGABYTES * 5)),
```

Move the file where it belongs with `move()`

```php
public static function uploadDocument(StoreDocumentRequest $request)
{
    /** @var Upload $upload */
    $upload = $request->validated('to-upload');

    $path = $upload->move(Storage::getInstance()->child('documents'), uniqid('document-').'.json');

    return Response::json(basename($path), StatusCode::CREATED);
}
```

| `Upload` | Gives you |
|---|---|
| `$inputName`, `$filename`, `$extension` | the input name, the client's file name and its extension |
| `$type`, `$size`, `$error`, `$tempName` | the declared type, the size in bytes, PHP's error code, the temporary path |
| `move(null\|Storage\|string $destination = null, ?string $newName = null)` | moves the file, returns its new path or `false` (the reason is logged) |
| `getPHPUploadErrorMessage()` | PHP's error code, explained |

`move()` defaults to the `Uploads` directory of your `Storage` and a generated name ; a string
destination is a directory inside your `Storage`. It refuses to overwrite an existing file.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./11-routing-and-middlewares.md">Previous : Routing and middlewares</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./13-controllers-and-responses.md">Next : Controllers and responses</a></div></td></tr></table>
