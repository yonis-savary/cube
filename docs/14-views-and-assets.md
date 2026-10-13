<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./13-controllers-and-responses.md">Previous : Controllers and responses</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./15-api-documentation.md">Next : Api documentation</a></div></td></tr></table>

# Views and assets

Cube renders plain-PHP views with the `Renderer` component : no template language, a view is a PHP
file of your application's `Views/` directory that prints HTML. The `Assets/` directory holds the CSS
and JavaScript files a view inlines with `AssetsInserter`, or that `AssetServer` serves by URL.

Here is a view and the route rendering it

```php
<!-- App/Views/order/details.php -->
<h1>Order <?= Cube\e($order->reference) ?></h1>
<table>
    <?php foreach ($order->lines as $line) { ?>
        <?= Cube\render('order/line', ['line' => $line]) ?>
    <?php } ?>
</table>
```

```php
public static function show(Request $request, Order $order)
{
    $order->load('lines');

    return Response::html(Cube\render('order/details', ['order' => $order]));
}
```

`Cube\render()` returns the HTML as a string : wrap it in `Response::html()` to answer it.

## Naming a view

A view is named by its path inside `Views/`, without the extension. The name matches the end of the
path, whole segments only

```
App/Views/client/details.php
App/Views/order/details.php
App/Views/order/line.php
```

| Name | Finds |
|---|---|
| `order/details` | `App/Views/order/details.php` |
| `order/line` | `App/Views/order/line.php` |
| `line` | `App/Views/order/line.php`, the only view of that name |
| `details` | the first of the two `details` views : give the directory to choose |
| `ails` | nothing, a name never matches part of a segment |

Every application declared in your configuration contributes its `Views/` directory (see
[Applications and discovery](./04-applications-and-discovery.md)), so the same name in two
applications finds the first one discovered. An unknown name throws an `InvalidArgumentException`.

## Passing data to a view

The context array becomes the view's variables : `['order' => $order]` gives `$order`

```php
Cube\render('order/details', ['order' => $order, 'showPrices' => true]);
```

A view only sees its own context. A view it renders sees nothing of its parent unless you pass it
again

```php
<!-- App/Views/order/details.php -->
<?= Cube\render('order/line', ['line' => $line]) ?> <!-- $order is not available inside order/line -->
```

A key that is not a valid PHP variable name (`'unit-price'`, `'0'`) is dropped.

When a view throws, its partial output is discarded and the exception goes up to your callback.

## Escaping values

A view prints nothing escaped by itself : wrap every value with `Cube\e()`, which converts it to a
string and escapes it for HTML, attributes included

```php
<a href="/clients/<?= Cube\e($client->id) ?>" title="<?= Cube\e($client->name) ?>">
    <?= Cube\e($client->name) ?>
</a>
```

Only print without `Cube\e()` what is already HTML, such as the result of another `Cube\render()`.

## Inlining assets

`Cube\asset()` reads a file of your `Assets/` directories and returns its content wrapped in a
`<style>` or `<script>` tag, ready to print in a view

```php
<!-- App/Views/client/details.php -->
<head>
    <?= Cube\asset('client.css') ?> <!-- <style>...</style> -->
</head>
<body>
    ...
    <?= Cube\asset('js/client.js') ?> <!-- <script>...</script> -->
</body>
```

| Extension | Inlined as |
|---|---|
| `.css` | `<style>` |
| `.js` | `<script>` |
| anything else | throws an `UnsupportedAssetTypeException` |

Asset names follow the same rule as view names : the end of the path, whole segments only, the
extension included. An unknown name throws an `InvalidArgumentException`.

## Serving assets

`AssetServer` serves the same `Assets/` files by URL, for a `<link>` or a `<script src>`. It is a
`WebAPI` : register it in your `RouterConfiguration`

```php
new RouterConfiguration(
    apis: [AssetServer::class],
)
```

```php
<link rel="stylesheet" href="/assets/client.css">
<script src="/assets/client.js"></script>
```

The default route is `/assets/{file}`, and a file matches when the requested name is the end of its
path, whole segments only — so `/assets/client.css` finds `App/Assets/css/client.css`. Pass another
route to the constructor if `/assets` does not suit you

```php
new AssetServer('/static/{file}');

// lets a request name a sub-directory, /assets/css/client.css
new AssetServer('/assets/{any:file}');
```

The file is answered with the content type of its extension. A request for an unknown asset answers
`404` with a plain-text message naming it.

To serve a whole front-end directory (a built single-page application, its `index.html` and its
files), use `StaticServer` instead — see [Static server](./32-static-server.md).

## The components

| Component | Does |
|---|---|
| `Renderer::getInstance()->render(string $viewName, array $context = [])` | what `Cube\render()` calls |
| `Renderer::getInstance()->findView(string $viewName)` | the path of a view, `null` when unknown |
| `AssetsInserter::getInstance()->insert(string $assetName)` | what `Cube\asset()` calls |
| `new Renderer(array $viewFiles)` / `new AssetsInserter(array $assetsFiles)` | a renderer or an inserter over other files, to swap in with `setInstance()` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./13-controllers-and-responses.md">Previous : Controllers and responses</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./15-api-documentation.md">Next : Api documentation</a></div></td></tr></table>
