<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./202-authentication.md">Previous : Authentication</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./204-http-client.md">Next : Http client</a></div></td></tr></table>

# User Component

`UserComponent` is a `Component` whose default instance depends on who is logged in. Use it when a
service must be scoped to the current user — a private storage directory, a per-user cache, a
preferences object — so that no call site has to pass the user id around.

```php
class UserPreferences
{
    use UserComponent;

    public function __construct(private Cache $cache) {}

    public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static
    {
        return new static(Cache::getInstance()->child("preferences-{$userPrimaryKeyMD5}"));
    }
}

// anywhere, no user id in sight
UserPreferences::getInstance()->set('theme', 'dark');
```

## What the trait gives you

`UserComponent` includes `Component`, so you get `getInstance()`, `setInstance()` and
`withInstance()` as usual. It then fixes how the default instance is built :

- `getDefaultInstance()` is `final` — it asks `Authentication` for the current user id and hands it
  to you. You do not override it.
- `getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5)` is `abstract` — this is where
  you build the instance for that user.
- `getAuthentication()` returns the `Authentication` component, and can be overridden if your
  service authenticates against something else.

Two arguments reach `getUserInstance()`

| Argument | What it is |
|---|---|
| `$userPrimaryKey` | the raw primary key, exactly as `Authentication::userId()` returns it |
| `$userPrimaryKeyMD5` | its MD5, safe to use as a directory name or a cache key |

Use the second whenever the value becomes part of a path or a key : a primary key can be a UUID, a
composite string, or anything else you would rather not concatenate into a filename.

## Nobody is logged in

`Authentication::userId()` returns `false` when there is no session, and that `false` is passed
straight through to `getUserInstance()`. Decide there what an anonymous instance means — a shared
guest scope, or a refusal

```php
public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static
{
    if (false === $userPrimaryKey)
        throw new RuntimeException('UserPreferences needs an authenticated user');

    return new static(Cache::getInstance()->child("preferences-{$userPrimaryKeyMD5}"));
}
```

The MD5 is still computed for `false`, so an anonymous scope is a real, stable key rather than an
empty one — every anonymous visitor shares it.

## The instance is resolved once

`Component` memoizes : the first `getInstance()` builds the instance and every later call returns
that same one. Within a web request that is what you want, since the user does not change.

It matters in the two places where a process outlives a login :

- **After a login**, an instance resolved earlier in the same request still belongs to the previous
  user. Drop it so the next call rebuilds it.
- **In a command or a queue worker** handling several users in a row, do the same between users.

```php
Authentication::getInstance()->login($user);
UserPreferences::removeInstance();     // next getInstance() is for $user
```

`withInstance()` is the cleaner form when the scope is bounded

```php
UserPreferences::withInstance($preferencesForThatUser, function () {
    // everything in here sees that instance
});
```

## Declaring the authentication first

`getDefaultInstance()` goes through `Authentication`, which needs an `AuthenticationConfiguration` in
your `cube.php` — see [Authentication](./202-authentication.md). Without one, the first
`getInstance()` fails while resolving that configuration, with an error about a missing constructor
argument rather than a message naming the real cause.

## Per-user storage

Cube ships `UserStorage`, an abstract `Storage` using the trait to give each user a subdirectory
named after their MD5. It is currently unusable as-is : `getUserInstance()` builds it with
`new self()`, which PHP refuses on an abstract class, so a subclass fails with *Cannot instantiate
abstract class*. Until it uses `new static()`, write the few lines yourself

```php
class InvoiceStorage extends Storage
{
    use UserComponent;

    public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static
    {
        $root = Storage::getInstance()->child('invoices')->child($userPrimaryKeyMD5);

        return new static($root->getRoot());
    }
}

InvoiceStorage::getInstance()->write('2026-01.pdf', $bytes);
```

Each user reads and writes inside their own directory, and no call site has to remember to scope the
path.
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./202-authentication.md">Previous : Authentication</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./204-http-client.md">Next : Http client</a></div></td></tr></table>
