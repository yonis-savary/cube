# Security and fixes roadmap

Outcome of the October 2026 audit. Each step names the tests reproducing it: they fail until the
fix is in place. To work on one step : `make test filter='testA|testB'`.

Order : exploitable flaws first, then data integrity, then functional correctness.

## 1. SQL injection and model integrity

- [x] **1.1 Identifier escaping** — a `{}` written between `` ` `` or `"` only got the driver's
  string escaping, which doubles neither the backtick nor the double quote : the value closed the
  identifier (`hasTable`, `hasField`, migration plans and adapters).
  - `DatabaseTest::testIdentifierPlaceholderCannotBeClosedByItsValue`
- [x] **1.2 UPDATE targets the original primary key** — `saveExisting()` filtered on the current
  primary key value : changing the key then calling `save()` overwrote another row.
  - `ModelTest::testSavingAChangedPrimaryKeyNeverTouchesAnotherRow`
- [x] **1.3 New or persisted** — `save()` picked an UPDATE as soon as the primary key was filled,
  so `insertArray()` with an explicit key inserted nothing. `saveNew()` read the id back through
  `last()` (wrong row under concurrent inserts or with an explicit key).
  - `ModelTest::testInsertingWithAnExplicitPrimaryKey`
- [x] **1.4 `destroy()` without primary key** — always returned before deleting.
  - `ModelTest::testDestroyAModelWithoutPrimaryKey`
- [x] **1.5 DateTime to SQL** — `TypeError` in `PDO::quote` on INSERT ; on UPDATE the time was lost
  (inverted ternary, 12-hour format).
  - `ModelTest::testInsertingADateTime`, `ModelTest::testUpdatingADateTimeKeepsItsTime`
- [x] **1.6 `__get` on a NULL field** — threw "does not have a … attribute".
  - `ModelTest::testReadingAFieldHoldingNull`
- [x] **1.7 `last()` on an empty table** — `TypeError` instead of `null`.
  - `ModelTest::testLastOnAnEmptyTable`

## 2. Query builder and migrations

- [x] **2.1 Alias without a dot in `fetch()`** — warning and lost value.
  - `QueryTest::testFetchAnAliasedExpression`, `QueryTest::testFetchAnAliasedField`
- [x] **2.2 `OFFSET` without `LIMIT`** — syntax error on MySQL and SQLite.
  - `QueryTest::testOffsetWithoutLimit`
- [x] **2.3 UPDATE with a join and no `where()`** — join conditions written without `WHERE`.
  - `QueryTest::testUpdateWithAJoinAndNoConditionKeepsItsWhereKeyword`
- [x] **2.4 Postgres plan** — `alterColumn` emits `DATETIME` and an empty `ALTER COLUMN` for a
  unique field, ignores DECIMAL precision ; `dropConstraint` quotes the name as a string.
  - `MigrationTest::testAlterColumnToDatetime`, `MigrationTest::testAlterColumnToUnique`
- [x] **2.5 Smaller fixes** — `ModelAPI` text search hard-coded backticks (broke Postgres),
  `reload()` of a missing row crashed on `null`, `id()` threw on a model without its primary key,
  `order()` accepted a `null` field it could not handle, a reloaded model stayed unpersisted so
  `save()` inserted it again. `HasOne::bind` reloading before saving is intended : it loads the row
  the foreign key points to.
  - `ModelAPITest::testReadingSearchesOnEveryDriver`, `ModelTest::testIdOfAModelWithoutItsPrimaryKey`,
    `ModelTest::testReloadingAMissingRowIsRefused`,
    `ModelTest::testSavingAReloadedModelUpdatesItsRow`

## 3. Files and path traversal

- [x] **3.1 `Storage` confinement** — `..` never resolved, `Path::relative` without a separator
  boundary, `Path::join` dropped a `"0"` part. `Storage` now resolves paths through
  `Path::confined()` ; `Path::relative()` keeps allowing `..` for project-relative configuration.
  - `StorageTest::test_path_does_not_escape_the_root`,
    `PathTest::testRelativeOnlyLeavesAPathUnderTheReferenceAlone`,
    `PathTest::testJoinKeepsAPartNamedZero`,
    `PathTest::testConfinedResolvesDotSegmentsInsideTheReference`
- [x] **3.2 LargeUpload** — a `../victim` (or `.`) identifier was accepted (writes and deletes
  outside the upload directory), `uniqid` identifiers were guessable.
  - `LargeUploadTest::testAnIdentifierCannotEscapeTheUploadDirectory`,
    `LargeUploadTest::testTheUploadDirectoryItselfIsNotAnUpload`,
    `LargeUploadTest::testIdentifiersAreNotDerivedFromTheClock`
- [x] **3.3 `%2F` in a slug** — decoded after matching. An untyped slug whose decoded value holds a
  `/` no longer matches ; `{any:…}` slugs keep it.
  - `RouteTest::testAnEncodedSlashDoesNotSplitIntoTheSlug`, `RouteTest::testAnAnySlugKeepsAnEncodedSlash`
- [x] **3.4 StaticServer** — served the source of `.php` files and dotfiles. The index is
  `index.html` only ; `secure` refuses `.php` files and dotfiles (`.well-known/` excepted).
  - `StaticServerTest::testAPhpIndexFileIsNotServedAsSource`, `StaticServerTest::testAPhpFileIsNotServed`,
    `StaticServerTest::testADotFileIsNotServed`, `StaticServerTest::testTheWellKnownDirectoryIsServed`

## 4. XSS and error responses

- [x] **4.1 422 responses** — had no Content-Type (so `text/html`) and reflected the input. Now
  `application/json`.
  - `RouterTest::testAnInvalidRequestAnswersJson`
- [x] **4.2 Reflected messages** — AssetServer 404, `ModelAPI` 422 and router 405 echoed a value
  without Content-Type. They use the new `Response::text()`.
  - `AssetServerTest::testAnUnknownAssetNameIsNotRenderedAsHtml`, `ResponseTest::testTextIsSentAsPlainText`,
    `RouterTest::testAKnownPathWithTheWrongMethodAnswersMethodNotAllowed`,
    `ModelAPITest::testUpdatingAnUnknownRowIsRefused`
- [x] **4.3 Exception handler** — showed message and trace as unescaped HTML when `environment` was
  missing, and read another key than `isProduction()`. Both read `env` then `environment` ; the
  trace is only shown when `Cube\isDebug()` (a key is set and is not production), as plain text.
  - `EnvironmentHelpersTest::testProductionAndDebugModes`

## 5. Authentication and sessions

- [x] **5.1 Nested guards** — the inner guard replaced the outer permissions. Each permission list
  now gets its own extras key, and the middleware sums every key it owns.
  - `AuthenticationMiddlewareTest::testANestedGuardKeepsTheOuterPermissions`
- [x] **5.2 Session fixation** — no `session_regenerate_id`, cookie without HttpOnly/SameSite.
  `login()` / `logout()` regenerate the id ; the cookie is `HttpOnly`, `Secure` over HTTPS,
  `SameSite` from `SessionConfiguration` (`Lax`), with `session.use_strict_mode`.
  - `AuthenticationTest::testLoggingInRenewsTheSessionId`
- [x] **5.3 RememberMe** — predictable `uniqid` token, old token never invalidated, any cache key
  accepted as a token, no SameSite. Tokens are 64 hex chars from `random_bytes()` and checked
  before any cache lookup, a refresh deletes the used token, `cookieSameSite` added.
  - `RememberMeTest::testTheTokenIsNotDerivedFromTheClock`,
    `RememberMeTest::testARefreshedTokenCannotBeReplayed`,
    `RememberMeTest::testACookieNamingAnotherCacheEntryIsIgnored`
- [x] **5.4 ModelAPI** — `ModelAPIConfiguration` (middlewares, extras) was never read. It now wraps
  every ModelAPI, around the group `getRouteGroup()` returns.
  - `ModelAPITest::testConfiguredMiddlewaresGuardTheApi`,
    `ModelAPITest::testConfiguredMiddlewaresWrapAnOverriddenRouteGroup`
- [x] **5.5 Websocket broadcast** — unauthenticated. Optional `broadcastSecret` in
  `WebsocketConfiguration` : when set, the HTTP endpoint answers `403` without the
  `X-Broadcast-Secret` header, which `Broadcast` sends ; when not, everything passes and the server
  logs a warning. `HttpClient::fetchAsync()` now sends `baseHeaders()` like `fetch()`.
  - `WebsocketRouterTest` (3 tests), `HttpClientTest::testAsyncFetchSendsTheBaseHeaders`
- [x] **5.6 Password hash in the session** — `PasswordAuthentication` returns its users without
  `passwordField` / `saltField`.
  - `AuthenticationTest::testThePasswordHashIsNotKeptInTheSession`

## 6. Validation (`Http\Rules`)

- [ ] **6.1 Stop after a failed type check** — the next steps still run : 500 instead of 422 on a
  mistyped input.
  - `ValidationTest::testAMalformedValueIsRefusedWithoutThrowing` (7 cases)
- [ ] **6.2 Composite rules** — their conditions and transformers are ignored.
  - `ValidationTest::testAConditionOnAnObjectIsChecked`,
    `ValidationTest::testAConditionOnAnArrayIsChecked`
- [ ] **6.3 Nullable object** — refuses `null`.
  - `ValidationTest::testANullableObjectAcceptsNull`
- [ ] **6.4 Lossy coercions** — `integer('1.9')`, `string(true)`, `"null"` in a form,
  `2024-02-31`. *Decision : how strict.*

## 7. Ambient services

- [ ] **7.1 Cache** — `child()` re-initialises the driver, TTL not checked in memory, a stored
  `null` replaced by the default, keys holding `/` never persisted.
  - `LocalDiskCacheTest` (4 tests)
- [ ] **7.2 Cached router** (after 7.1) — `Cache::set` calls the `Route` (`__invoke`), key without
  the HTTP method. *Test still to write.*
- [ ] **7.3 Loggers** — `getInstance()` returns the wrong subtype, shared stdout gets closed.
  - `LoggerTest::test_a_logger_subclass_gets_an_instance_of_its_own_type`,
    `LoggerTest::test_destroying_a_stdout_logger_keeps_the_shared_stream_open`
- [ ] **7.4 Injector** — `LoggerInterface` not resolved, provided invokable object gets called,
  provided scalar.
  - `InjectorTest::test_a_psr_logger_interface_is_resolved_to_cube_logger`,
    `InjectorTest::test_a_provided_invokable_object_is_handed_as_is`,
    `InjectorTest::test_a_provided_scalar_is_rejected_like_a_mismatching_object`
- [ ] **7.5 Autoloader and helpers** — `uses()` misses inherited traits, `function_exists('env')`
  guard, APCu snapshot ignoring the configuration and the `composer.lock` hash.
  - `AutoloaderTest::test_uses_predicate_sees_a_trait_inherited_from_a_parent`
- [ ] **7.6 Environment** — a `.env` holding `=` or `!` is dropped entirely, `getenv()` never read.
  - `EnvironmentTest::testValuesHoldingIniOperatorsAreRead`

## 8. Utilities and crypto

- [ ] **8.1 `encrypt` / `decrypt`** — `"0"` and `""` refused ; CBC without MAC, key not derived.
  *Decision : moving to AES-GCM makes already encrypted data unreadable.*
  - `EncryptionTest::testFalsyPlaintextsSurviveARoundTrip`,
    `EncryptionTest::testATamperedCiphertextIsRejected`
- [ ] **8.2 Bunch** — `min`/`max` of one element, `key` on null, associative `flat`, `first` with a
  callback returning an int.
  - `BunchTest::testMinAndMaxOfASingleElement`, `BunchTest::testKeyReturnsAFieldHoldingNull`,
    `BunchTest::testFlatOnAssociativeRows`, `BunchTest::testFirstAcceptsATruthyResult`
- [ ] **8.3 Text** — `interpolate` re-expands values, `dontEndsWith('')` loops forever.
  - `TextTest::testInterpolateDoesNotExpandPlaceholdersFoundInValues`
- [ ] **8.4 Misc** — `pathToNamespace`, empty job file, `/products/?page=2`, `.` in routes,
  interactive `Console` helpers, `HttpClient` redirects.
  - `PathTest::testPathToNamespaceFollowsThePsr4Prefixes`,
    `LocalDiskQueueDriverTest::test_an_empty_job_file_gives_nothing_to_process`,
    `RequestTest::testATrailingSlashBeforeTheQueryStringIsTrimmed`,
    `RouteTest::testADotInAStaticPartIsLiteral`
- [ ] **8.5 Cron** — `*/2` (day of month) and `*/6` (month) don't start on 1.
  *Decision : standard cron or current behaviour, which existing tests assert.*
  - `CronExpressionTest::testADayOfTheMonthStepStartsOnTheFirst`,
    `CronExpressionTest::testAMonthStepStartsOnJanuary`
