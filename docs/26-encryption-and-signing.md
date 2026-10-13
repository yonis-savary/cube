<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./25-remember-me.md">Previous : Remember me</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./27-http-client.md">Next : Http client</a></div></td></tr></table>

# Encryption and signing

Cube encrypts values you need to read back with `Cube\encrypt()` / `Cube\decrypt()`, and signs data it
stores outside your application with `SignedPayload`. Both are keyed on your `APP_KEY`.

```php
$stored = encrypt($agency->iban);   // "v2:..." : safe to put in a column or a cookie
$iban = decrypt($stored);           // the original string
```

## The application key

`APP_KEY` comes from your `.env` file and must hold at least 32 bytes. Generate one with

```sh
php do key:generate
# APP_KEY=...   (32 random bytes, base64-encoded)
```

A missing key, or one shorter than 32 bytes, throws a `RuntimeException` the first time something
needs it — not at boot. An application that never encrypts and uses no Redis cache or queue can run
without one.

Use the same `APP_KEY` on every server and worker of an application : a value encrypted or signed
with one key cannot be read with another. See [Installation](./02-installation.md#the-application-key).

## Encrypting a value

`encrypt()` and `decrypt()` work on strings. Serialize anything else yourself

```php
$token = encrypt(json_encode(['agency' => $agency->id, 'expires' => time() + 3600]));

$payload = json_decode(decrypt($token), true);
```

`decrypt()` throws an `Exception` when the value was not produced by `encrypt()`, was modified, or
was encrypted with another key. When the value comes from a client, catch it and answer as you would
for any invalid input

```php
try {
    $payload = json_decode(decrypt($request->param('token')), true);
} catch (Exception) {
    return Response::badRequest('Invalid token');
}
```

Both functions take a key as their second argument, to use another key than `APP_KEY` — it must hold
at least 32 bytes as well

```php
$stored = encrypt($agency->iban, $bankingKey);
$iban = decrypt($stored, $bankingKey);
```

## What you get

| Property | Detail |
|---|---|
| Cipher | AES-256-GCM, authenticated : a modified value fails to decrypt instead of decrypting to garbage |
| Key | derived from `APP_KEY` with HKDF-SHA256, so the whole key counts, whatever its length |
| Nonce | random, so encrypting the same value twice gives two different strings |
| Format | `v2:` followed by base64 of nonce, tag and ciphertext |
| Keys in traces | the content and key arguments are `#[SensitiveParameter]`, so they never reach a logged stack trace |

`encrypt()` and `decrypt()` are shortcuts for `Encryption::encrypt()` and `Encryption::decrypt()`.
`Encryption::supports($value)` tells whether a string has the current format.

## Values encrypted by an older Cube

Before `Encryption`, Cube encrypted with AES-256-CBC, without authentication and without the `v2:`
prefix. `decrypt()` refuses those values on purpose : reading an unauthenticated format from any
input would let a client probe it. Read them explicitly with `LegacyEncryption`, and re-encrypt them

```php
$iban = Encryption::supports($agency->iban_encrypted)
    ? decrypt($agency->iban_encrypted)
    : LegacyEncryption::decrypt($agency->iban_encrypted);

$agency->iban_encrypted = encrypt($iban);
$agency->save();
```

`LegacyEncryption` is deprecated and only decrypts : run such a migration once, then stop using it.

## Signed payloads

`SignedPayload` prepends an HMAC-SHA256 of the payload, keyed on `APP_KEY`, so you can check that data
stored outside your application was written by it before trusting it. The payload stays readable :
this is about integrity, not secrecy.

```php
$signed = SignedPayload::sign(serialize($report));

// later, after reading it back from a shared store
if (null === $payload = SignedPayload::verify($signed))
    throw new RuntimeException('Report payload was not signed by this application');

$report = unserialize($payload);
```

`verify()` returns the original payload, or `null` when the signature does not match. Like `encrypt()`,
both methods take another key as their second argument.

Sign anything you are about to `unserialize()` from a place another process can write to : an
unserialized value can build objects of your classes.

## Where Cube signs

| Component | Signs | When the signature does not match |
|---|---|---|
| `RedisCache` | every cached value | the value is ignored, a warning is logged |
| `RedisQueue` | every job | the job is dropped, a warning is logged |

So changing `APP_KEY` in effect empties a Redis cache, and drops the Redis jobs still waiting. See
[Storage and caching](./21-storage-and-caching.md) and [Queues](./29-queues.md).

## Changing the key

Cube reads one key only : there is no list of previous keys. Changing `APP_KEY`

- makes every value encrypted with the old key unreadable : decrypt them first and re-encrypt them
  with the new one
- invalidates the Redis cache and the waiting Redis jobs, as above

## Reference

| Function or method | Does |
|---|---|
| `Cube\encrypt(string $content, ?string $key = null): string` | encrypts with `APP_KEY` or the given key |
| `Cube\decrypt(string $encrypted, ?string $key = null): string` | decrypts, throws when the value is invalid |
| `Cube\appKey(?string $key = null): string` | the given key or `APP_KEY`, throws when missing or shorter than 32 bytes |
| `Encryption::supports(string $encrypted): bool` | whether the value has the current `v2:` format |
| `LegacyEncryption::decrypt(string $encrypted, ?string $key = null): string` | reads a value of the former format (deprecated) |
| `SignedPayload::sign(string $payload, ?string $key = null): string` | prepends the signature |
| `SignedPayload::verify(string $signed, ?string $key = null): ?string` | the payload, `null` when the signature does not match |

| Command | Does |
|---|---|
| `php do key:generate` | prints a random `APP_KEY` line to paste in your `.env` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./25-remember-me.md">Previous : Remember me</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./27-http-client.md">Next : Http client</a></div></td></tr></table>
