<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./27-encryption-and-signing.md">Previous : Encryption and signing</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./29-http-client.md">Next : Http client</a></div></td></tr></table>
# Identifiers and secrets

`Identifier` generates UUIDs and prefixed secrets, and hashes the secrets you hand out once and never
read back, such as API tokens or webhook signing secrets.

```php
$agency->uuid = Identifier::uuid6();          // "1f0c5e2a-7b3d-6a10-9f2e-0b1c2d3e4f50" : sorts by creation time

$secret = Identifier::prefixed('whsec_');     // "whsec_" + 43 random characters, shown once to the user
$webhook->secret_hash = Identifier::hash($secret);

// later, when the client presents it
if (!Identifier::matches($request->getHeader('x-webhook-secret') ?? '', $webhook->secret_hash))
    return Response::unauthorized();
```

## UUIDs

Both versions follow RFC 9562 and come out as lowercase `8-4-4-4-12` strings, ready for
`Param::uuid()` and the `{uuid:id}` route slug.

| Method | Made of | Use it for |
|---|---|---|
| `Identifier::uuid4()` | 122 random bits from `random_bytes()` | identifiers that must reveal nothing, not even when they were made |
| `Identifier::uuid6()` | a timestamp at 100 ns precision, then random bits | primary keys : sorting them sorts by creation time, which keeps database indexes compact |

A v6 reveals the moment it was generated : prefer a v4 for anything a client sees and should not
date.

Within one process, `uuid6()` never gives the same value twice and always gives a greater one than
the previous call, even several times in the same 100 ns. Between processes, uniqueness comes from
random bits drawn once per process ; a process forked after generating identifiers draws its own.

## Prefixed identifiers and secrets

`prefixed()` writes your prefix followed by random bytes, encoded in base64url (letters, digits, `-`
and `_`, no padding). The prefix tells what a value is when it shows up somewhere it should not : a
log line, a commit, a support ticket. Secret scanners rely on it.

```php
Identifier::prefixed('whsec_');       // 32 random bytes : 43 characters after the prefix
Identifier::prefixed('cus_', 16);     // 16 random bytes : 22 characters after the prefix
```

Fewer than 16 bytes throws an `InvalidArgumentException` : below 128 random bits, a value can be
guessed.

## Storing a secret you never read back

Keep only a hash of a secret you hand out, so that reading your database is not enough to use it.
`hash()` computes an HMAC-SHA256 keyed on your `APP_KEY`, and `matches()` compares in constant time

```php
$token = Identifier::prefixed('api_');
ApiToken::insertArray(['user_id' => $user->id, 'hash' => Identifier::hash($token)]);

// on each request
$owner = ApiToken::findWhere(['hash' => Identifier::hash($bearerToken)])?->user_id;
```

Since the hash is deterministic, you can look a secret up by its hash, as above : that is why these
methods suit random secrets and not passwords. Hash passwords with `password_hash()`, see
[Authentication](./23-authentication.md#password-authentication).

`hash()` throws a `RuntimeException` without an `APP_KEY`, and changing the key makes every stored
hash unmatchable : the secrets have to be issued again. See
[Encryption and signing](./27-encryption-and-signing.md#the-application-key).

`hash256()` and `matches256()` hash with plain SHA-256, without key. They survive a key change, but
are **less secure** : whoever reads your hashes can test guesses against them without your
`APP_KEY`. Use them only for secrets that outlive the application key, or that another system has to
check.

## Reference

| Method | Returns |
|---|---|
| `Identifier::uuid4(): string` | a random UUID version 4 |
| `Identifier::uuid6(): string` | a time-ordered UUID version 6 |
| `Identifier::prefixed(string $prefix, int $bytes = 32): string` | the prefix followed by `$bytes` random bytes in base64url |
| `Identifier::hash(string $secret, ?string $key = null): string` | the HMAC-SHA256 of the secret, keyed on `APP_KEY` or the given key |
| `Identifier::matches(string $secret, string $hash, ?string $key = null): bool` | whether the secret has that `hash()`, in constant time |
| `Identifier::hash256(string $secret): string` | the plain SHA-256 of the secret (less secure) |
| `Identifier::matches256(string $secret, string $hash): bool` | whether the secret has that `hash256()`, in constant time |
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./27-encryption-and-signing.md">Previous : Encryption and signing</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./29-http-client.md">Next : Http client</a></div></td></tr></table>
