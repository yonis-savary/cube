# Guidelines

Rules for writing code in this repository. When one of them conflicts with an existing pattern in
the file you are editing, the existing pattern wins — consistency beats cleverness.

## Use the framework before writing anything

Cube already provides most plumbing. Before implementing, look for it:

| Need | Use |
|---|---|
| a collection pipeline | `Bunch` (not `array_map`/`array_filter` chains) |
| a singleton / ambient service | `use Component` + `getDefaultInstance()` |
| build an object with its dependencies | `Injector::instanciate()` / `inject()` |
| find every class of a kind | `Bunch::fromExtends()` / `fromImplements()` / `fromUses()` |
| read configuration | a `ConfigurationElement` subclass + `::resolve()` |
| read an env value | `Cube\env()` (configuration files only — they get cached) |
| paths | `Utils\Path`, `Env\Storage` (never raw `__DIR__` concatenation) |
| strings | `Utils\Text` (`interpolate`, `dontEndsWith`, …) |
| SQL | `Query` + `ModelField`, never a concatenated string |
| input shape | `Http\Rules\Param` in a `Request` subclass |
| an HTTP call | `(new Request(...))->fetch()` / `HttpClient` |
| react to something | `Event` + `EventDispatcher::on()` |

## Code shape

- **Guard clauses only.** Reject early, return early, happy path at the left margin. No `else`
  after a `return` or `throw`.
- **One-shot conditions and loops stay one statement**, no accumulated nesting around them:
  ```php
  if (!$this->cache)
      return false;

  foreach ($values as $value)
      $this->add($value);
  ```
  (Brace style is the one open question — see the end of this file.)
- **Nesting budget: two.** A third level means extracting a method or inverting the condition.
  Loop bodies start with `continue` guards.
- **Lazy defaults at the top of the body**, so signatures stay optional:
  ```php
  public function save(?Database $database = null): self
  {
      $database ??= Database::getInstance();
  ```
  Every method that touches an ambient service takes it as a nullable last parameter and resolves
  it this way — that is what makes the test harness able to swap it.
- **`match` and lookup tables over branch ladders** (`ModelField::toRule()`, `Route::SLUG_FORMATS`).
  A `switch` survives only for genuinely positional / fall-through cases.
- **Recursion for recursive data** (route groups, relation trees, nested validation), with the
  accumulated path passed down as a parameter.
- Formatting comes from `.php-cs-fixer.dist.php` (`make fix`) — do not hand-tune alignment.

## Typing and PHPDoc

The framework's value is that an IDE knows everything. This is not optional.

- Type every parameter, property and return. `mixed` only when it genuinely is.
- Generics through PHPDoc: `@template`, `@param class-string<TClass>`, `@return TClass`,
  `Bunch<int,TValue>`, `Query<static>`, `@return array<string,ModelField>`.
- **Preserve subtype identity**: fluent and factory methods on a base class return `static`, so
  subclasses stay usable without a cast.
- **No magic.** No `__call`, no dynamic method dispatch, no string-keyed container. `Model`'s
  `__get`/`__set` are the single exception and are paid for with generated `@property` docblocks —
  do not add a second one.
- Public entry points get a docblock saying what the type is *for* and how to enter it (see
  `Queue`, `Test\CubeTestCase`). A docblock restating the signature is noise; delete it.

## Class design

- Know which species you are writing: **service** (behaviour, mutable, often a `Component`),
  **value object** (data, immutable, public readonly fields, no accessors — `Query\Field`,
  `ModelField`), **contract** (one-role interface — `Middleware`, `Relation`,
  `CacheDriverInterface`), **mixin** (trait named for a capability — `Component`, `HasLogger`,
  `HasScopedSession`, `UserComponent`).
- **No interface without a second implementation in sight.**
- **Extension by addition.** A new backend, command, migration, rule or route must be one new
  file. If it also requires editing a registry, a `switch` or a central array, the design is
  wrong — make the class self-declare instead (`supports()`, `handle()`, an abstract method).
- **Three-tier contracts on an extension point**: `abstract` for what a subclass must supply, an
  overridable method with a default for what it may, `final` for the skeleton it must not touch
  (`Command::getFullIdentifier()`, `UserComponent::getDefaultInstance()`).
- Static means class-level or stateless: named constructors and the declarations a subclass must
  answer. Anything holding state is an instance.

## Naming vocabulary

Closed set, fixed meanings — follow it:

- `from*` alternate constructors: `Request::fromGlobals()`, `Model::fromArray()`,
  `Bunch::fromExtends()`.
- `to*` conversions: `toArray()`, `toRule()`, `toObjectParam()`, `toBunch()`.
- `with*` fluent builder steps returning `static`: `withBaseModel()`, `withCondition()`,
  `withMetadata()`, `withInstance()`.
- `get*` cheap accessors, `is*`/`has*` predicates, `assert*` throwing checks.
- Plural names mean collections (`uploads()` vs `upload()`) — two names beat one name plus a
  "multiple" flag.
- Traits are named for the capability they add, interfaces for the role they confer.

## Boundaries and failure

- **Normalize on the way in, coerce on the way out.** `Request` parses once at the edge,
  `Response`/`RouterCallStack` converts models and collections on the way out. Nothing in between
  re-guesses formats.
- **Validate once, then trust.** Inside the boundary, no defensive re-validation and no fallback
  for a state that cannot occur.
- **Two audiences, two mechanisms.** A misused API or missing configuration throws where it is
  detected (`InvalidArgumentException`, `RuntimeException`). Invalid *user input* returns a
  `ValidationReturn`. Never an exception for expected user behaviour, never a status code for a
  programmer error.
- To abort a request from deep inside, throw `ResponseException` carrying the finished `Response`.
- **Diagnostics, not labels**: name the type, the field, the actual value and the expected one —
  `"Could not find adapter for [{$driver}] database"`.
- Logging uses templated messages plus a context map, never concatenation:
  `Logger::getInstance()->warning('Cannot load {app} directory', ['app' => $app])`.

## Comments

Default to none; naming and structure carry the *what*. Roughly one comment per file, for a
non-obvious optimisation and why it is safe (`Route::match()`), a deliberate indirection, a case
intentionally left unhandled, or a `TODO` with a concrete follow-up. Never restate code, never
leave commented-out code, never narrate a refactor.

## Tests

- Every feature or fix ships with a test. Unit tests live in `tests/units/<Concern>` under
  `Cube\Tests\Units\*` and mirror `src/`; behaviour that needs a real app goes to
  `tests/integration` against `tests/integration-root/App`.
- Extend `PHPUnit\Framework\TestCase` for pure units; extend `Cube\Test\CubeTestCase` when you
  need a fresh database and the HTTP helpers (`$this->post('/x')->assertOk()`).
- Swap ambient services instead of mocking the world: `Component::withInstance()` /
  `$instance->asGlobalInstance(fn() => ...)`, or pass the collaborator explicitly.
- `phpunit.xml` runs with `stopOnFailure`/`failOnWarning` — a deprecation is a failure.
- Run `make test` (needs docker) or `make workflow-test` when the services are already up;
  `make test filter=SomeTest` to narrow. `make cleanup` if a run left artefacts behind.

## Documentation

Durable knowledge belongs in the repository. A new subsystem gets a page in `docs/` (numbered,
`1xx` core / `2xx` features), is added to `docs/README.md`, and the menus are regenerated with
`php docs/generate-menus.php`. Update `README.md`'s feature list when the framework gains a
capability.

## Git

- Never commit, push or branch unless asked; never work directly on `main`.
- One concern per commit, message about *why*. The log style here is short and prefixed:
  `hotfix: ...`, `Added Model::merge() : allow patching through assoc array`,
  `StaticServer: Fixed potential security issue`.
