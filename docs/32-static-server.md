<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./31-unix-socket-server.md">Previous : Unix socket server</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./33-testing.md">Next : Testing</a></div></td></tr></table>

# Static server

`StaticServer` serves the files of a directory over HTTP. It is a `WebAPI`, so it gets a look at
every request *before* the router walks its routes : when the path matches a file it answers with
it, otherwise it steps aside and routing continues.

Register it in your `RouterConfiguration`

```php
new RouterConfiguration(
    apis: [
        new StaticServer('FrontEnd/Public'),
    ]
)
```

That is enough to serve `FrontEnd/Public/app.js` at `/app.js`, and `FrontEnd/Public/index.html` at
`/`.

## What it serves

The directory is the root, and the request path is resolved inside it. Give the constructor a path
relative to your project (an existing absolute or working-directory path is used as is), or a
`Storage` you already have

```php
new StaticServer('App/Static');
new StaticServer(Storage::getInstance()->child('Public'));
```

When the path is `/`, the server answers with `index.html`. If the directory has none when the server
is built, it never answers for `/`, and your routes handle it. The files are sent as they are, with
the content type of their extension, never executed : a PHP page belongs in a route.

| Request | Answer |
|---|---|
| `/my-file.txt`, and the file exists in the directory | the file |
| `/`, with an `index.html` present | the index file |
| anything else | nothing — the router carries on |

`StaticServer` is checked before your routes, so a file in the directory hides a route with the same
path.

## The path check

A static server is the classic way to leak a filesystem, so it refuses a path before touching it.
The check is on by default and can be turned off with `secure: false`. Whatever the option, `..`
never leaves the directory : the path is resolved inside it, as any `Storage` path is.

```php
new StaticServer('App/Static', secure: false);   // only if you know why
```

The check refuses a path that resolves outside the directory through a symlink, any path ending in
`.php`, and any file or directory whose name starts with a dot (`.env`, `.git/`) — except
`.well-known/`. A path resolving to nothing is refused too : there is nothing to serve.

The check is on the *resolved* path, not on the text of the request, so the served directory is a
real boundary and a file name is only a file name

```
GET /etc/passwd        -> App/Static/etc/passwd when that file exists, otherwise nothing
GET /../../secrets     -> App/Static/secrets when that file exists, `..` stops at the directory
GET /link.txt          -> refused when link.txt is a symlink pointing outside
GET /config.php        -> refused, PHP sources are never served
GET /.env              -> refused, and so is everything under /.git/
GET /.well-known/x.txt -> served
GET /backup..2024.txt  -> served, two dots in a file name are not a traversal
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

The method does nothing when the directory has no index file, or when the server was built with
`supportsIndex: false`.

## Serving assets

`AssetServer` is a different thing : instead of one directory, it serves the files Cube discovered in
the `Assets/` directories of your applications, wherever they are. It is described with the other
asset tools in [Views and assets](./14-views-and-assets.md#serving-assets).

Use `StaticServer` for a whole directory you publish as is (a built front-end, downloadable files),
and `AssetServer` for the CSS and JavaScript files that live next to your application code.

## Options

| Option | Default | Does |
|---|---|---|
| `$directory` | required | path relative to the project, or a `Storage` |
| `$secure` | `true` | refuse symlinks escaping the directory, `.php` paths and dotfiles |
| `$supportsIndex` | `true` | answer `/` with `index.html` when it exists, and allow `registerFallbackRoute()` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./31-unix-socket-server.md">Previous : Unix socket server</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./33-testing.md">Next : Testing</a></div></td></tr></table>
