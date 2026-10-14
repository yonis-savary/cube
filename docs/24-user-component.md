<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./23-authentication.md">Previous : Authentication</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./25-remember-me.md">Next : Remember me</a></div></td></tr></table>

# User component

`UserComponent` is a [component](./06-components.md) whose default instance depends on who is logged
in. Use it when a service must be scoped to the current user — a private storage directory, a
per-user cache, a preferences object — so that no call site has to pass the user id around.

```php
class UserPreferences
{
    use UserComponent;

    public function __construct(private Cache $cache) {}

    public static function getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static
    {
        return new static(Cache::getInstance()->child("preferences-{$userPrimaryKeyMD5}"));
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache->set($key, $value);
    }
}

// anywhere, no user id in sight
UserPreferences::getInstance()->set('theme', 'dark');
```

## What the trait gives you

`UserComponent` includes `Component`, so you get `getInstance()`, `setInstance()`,
`removeInstance()` and `withInstance()` as usual. It then fixes how the default instance is built :

- `getDefaultInstance()` is `final` — it asks `Authentication` for the current user id and hands it
  to you. You do not override it.
- `getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5)` is `abstract` — this is where
  you build the instance for that user.
- `getAuthentication()` returns the `Authentication` component, and can be overridden in the class
  using the trait, or in any subclass of it, if your service authenticates against something else.

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
your `cube.php` — see [Authentication](./23-authentication.md). Without one, the first
`getInstance()` fails while resolving that configuration, with an error about a missing constructor
argument rather than a message naming the real cause.

## Per-user storage

Cube ships `UserStorage`, an abstract [`Storage`](./21-storage-and-caching.md) using the trait to give
each user a subdirectory named after their MD5, under a directory named after your class (lowercased,
namespace separators turned into `/`), on the driver of the global `Storage`. Nobody logged in means no directory : `getInstance()` throws a
`RuntimeException` rather than sharing one between every anonymous visitor.

```php
class InvoiceStorage extends UserStorage {}

InvoiceStorage::getInstance()->write('2026-01.pdf', $bytes);
```

Each user reads and writes inside their own directory, and no call site has to remember to scope the
path.

## Reference

| Member of `UserComponent` | Tier | Does |
|---|---|---|
| `getUserInstance(mixed $userPrimaryKey, string $userPrimaryKeyMD5): static` | `abstract` | builds the instance for that user |
| `getAuthentication(): Authentication` | overridable | where the current user id comes from |
| `getDefaultInstance(): static` | `final` | reads the user id, computes its MD5, calls `getUserInstance()` |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./23-authentication.md">Previous : Authentication</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./25-remember-me.md">Next : Remember me</a></div></td></tr></table>
