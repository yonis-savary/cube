<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./206-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./README.md">Next : Readme</a></div></td></tr></table>

# Large Upload

A file too big for one request is sent in pieces. `LargeUploadManager` gives each transfer an
identifier and a directory, collects the chunks as they arrive, and concatenates them when the client
says it is done.

```
client                          your controller                   Storage
  |                                   |                              |
  |-- POST /uploads ----------------->| $manager->start()            |
  |<-- identifier --------------------|                     large-upload-temp/<id>/
  |                                   |                              |
  |-- PUT /uploads/<id>/0 ----------->| $upload->addChunk(0, $body)  chunk-0
  |-- PUT /uploads/<id>/1 ----------->| $upload->addChunk(1, $body)  chunk-1
  |                                   |                              |
  |-- POST /uploads/<id>/done ------->| $upload->wrap($destination)  -> one file
  |<-- final path --------------------|                     temp directory removed
```

Chunk numbers decide the order, not arrival time, so the client may send them in parallel.

## Opening a transfer

`start()` creates the identifier and its directory. Give the identifier back to the client — every
following call needs it.

```php
public static function beginUpload(Request $request)
{
    $upload = LargeUploadManager::getInstance()->start();

    return Response::json(['identifier' => $upload->identifier]);
}
```

## Receiving chunks

`find()` returns the transfer, or `null` when the identifier is unknown — which is also how you
reject a client inventing one.

```php
public static function pushChunk(Request $request, string $identifier, int $number)
{
    if (!$upload = LargeUploadManager::getInstance()->find($identifier))
        return Response::notFound('Unknown upload');

    $upload->addChunk($number, $request->getBody());

    return Response::noContent();
}
```

A chunk is written as `chunk-<number>` in the transfer's directory. Sending the same number twice
overwrites it, so a client can retry a failed piece without restarting.

## Closing it

`wrap()` concatenates the chunks in natural order into one file, and returns its path. The transfer
directory is removed afterwards unless you ask otherwise.

```php
public static function finishUpload(Request $request, string $identifier)
{
    if (!$upload = LargeUploadManager::getInstance()->find($identifier))
        return Response::notFound('Unknown upload');

    $path = $upload->wrap(Storage::getInstance()->child('documents'));

    return Response::json(['path' => $path]);
}
```

The destination file is named after the identifier. `wrap()` **throws** when that name already exists
in the destination, rather than overwriting — rename or move the result if you want a name of your
own.

```php
$path = $upload->wrap($destination, cleanup: false);   // keep the chunks
```

## Giving up

An abandoned transfer leaves its chunks behind. `delete()` removes the directory and everything in
it, and answers `false` when the identifier is unknown.

```php
LargeUploadManager::getInstance()->delete($identifier);
```

Nothing expires old transfers on its own. Each directory holds an `info.json` carrying its
`creation_date`, which is what a scheduled cleanup would read — see
[Schedule and queues](./205-schedule-and-queues.md).

## Reference

| `LargeUploadManager` | Does |
|---|---|
| `start()` | opens a transfer and returns the `LargeUpload` |
| `find(string $identifier)` | returns the transfer, `null` when unknown |
| `delete(string $identifier)` | removes it, `false` when unknown |

| `LargeUpload` | Does |
|---|---|
| `$identifier` | the public identifier, readonly |
| `$storage` | the `Storage` holding this transfer's chunks, readonly |
| `addChunk(int $chunkNumber, string $body)` | writes one chunk |
| `wrap(Storage $destination, bool $cleanup = true)` | concatenates and returns the final path |
| `delete()` | removes the chunks and the directory |

Configure the working directory in your `cube.php`

```php
new LargeUploadManagerConfiguration(
    storageName: '/large-upload-temp',
)
```

| Option | Default | Does |
|---|---|---|
| `storageName` | `/large-upload-temp` | directory, inside your `Storage`, holding the transfers |
| `maxSize` | `null` | reserved — nothing reads it yet, cap the size in your own rule |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./206-websockets.md">Previous : Websockets</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./README.md">Next : Readme</a></div></td></tr></table>
