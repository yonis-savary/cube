<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./111-models.md">Previous : Models</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./202-authentication.md">Next : Authentication</a></div></td></tr></table>

# Static Server

`StaticServer` serves a directory over HTTP. It is a `WebAPI`, so it gets a look at every request
*before* the router walks its routes : when the path matches a file it answers with it, otherwise it
steps aside and routing continues.

Register it in your `RouterConfiguration`

```php
new RouterConfiguration(
    apis: [
        new StaticServer('FrontEnd/Public'),
    ]
)
```

That is enough to serve `FrontEnd/Public/app.js` at `/app.js`.

## What it serves

The directory is the root, and the request path is resolved inside it. Give the constructor a path
— relative to your project — or a `Storage` you already have.

```php
new StaticServer('App/Static');
new StaticServer(Storage::getInstance()->child('Public'));
```

When the path is `/`, the server looks for an index file, trying `index.php` then `index.html`. If
neither exists the server simply never answers for `/`, and your routes handle it.

| Request | Answer |
|---|---|
| `/my-file.txt`, and the file exists in the directory | the file |
| `/`, with an index file present | the index file |
| anything else | nothing — the router carries on |

## The path check

A static server is the classic way to leak a filesystem, so it refuses a path before touching it.
The check is on by default and can be turned off with `secure: false`.

```php
new StaticServer('App/Static', secure: false);   // only if you know why
```

A path is refused when it contains `..`, or when it resolves to a real file or directory **outside**
the served directory. The order matters : what exists inside the directory is served first, so a
legitimate `App/Static/etc/passwd` is still served at `/etc/passwd` — it is the system file of the
same name that is out of reach.

```
GET /etc/passwd     -> App/Static/etc/passwd when that file exists, otherwise nothing
GET /tmp/whatever   -> refused, that is a real path on the host
GET /../../secrets  -> refused, it contains ..
```

## Single-page applications

A front-end router needs every unknown path to return the index file. `registerFallbackRoute()`
declares that catch-all for you — call it from a routes file, since nothing calls it automatically

```php
// App/Routes/frontend.php
(new StaticServer('FrontEnd/Public'))->registerFallbackRoute($router);
```

It adds a `GET /{any:any}` route serving the index file. Declare it last : a catch-all matches
everything, so any route declared after it is unreachable.

The method does nothing when the directory has no index file.

## Serving assets

`AssetServer` is a different thing : instead of one directory, it serves the files Cube discovered in
the `Assets/` directories of your applications, wherever they are.

```php
new RouterConfiguration(
    apis: [
        AssetServer::class,
    ]
)
```

The default route is `/assets/{file}`, and a file matches when the requested name is the end of its
path — so `/assets/app.css` finds `App/Assets/css/app.css`. Pass another pattern to the constructor
if `/assets` does not suit you

```php
new AssetServer('/static/{file}');
```

In a view, `Cube\asset()` gives you the URL rather than hard-coding it

```php
<link rel="stylesheet" href="<?= Cube\asset('app.css') ?>">
```

A request for an unknown asset answers `404` with the name that was not found.

## Options

| Option | Default | Does |
|---|---|---|
| `$directory` | required | path relative to the project, or a `Storage` |
| `$secure` | `true` | refuse paths escaping the directory |
| `$supportsIndex` | `true` | look for `index.php` then `index.html` to answer `/` |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./111-models.md">Previous : Models</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./202-authentication.md">Next : Authentication</a></div></td></tr></table>
