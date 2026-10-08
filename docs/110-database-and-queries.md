<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./109-events.md">Previous : Events</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./111-models.md">Next : Models</a></div></td></tr></table>

# Database and queries

The `Database` component owns your SQL connection : it is a thin layer above `PDO` that knows how
to interpolate values safely, and it hands every query to the `QueryBuilder` matching your driver.

Two ways to talk to it, and you can mix them freely

```php
// Raw SQL, with placeholders
Database::getInstance()->query('SELECT * FROM product WHERE name = {}', ['screen']);

// Query objects, built step by step
Product::select()->where('name', 'screen')->fetch();
```

The second form is covered by [Models](./111-models.md) for the model side of things ; this page
describes the connection and the query builder underneath.

## Configuration

Declare a `DatabaseConfiguration` in your `cube.php`

```php
new DatabaseConfiguration(
    driver: 'mysql',
    database: env('DB_NAME', 'app'),
    host: env('DB_HOST', '127.0.0.1'),
    port: 3306,
    user: env('DB_USER'),
    password: env('DB_PASSWORD'),
)
```

| Driver | DBMS |
|--------|------|
| `sqlite` | SQLite — `database` is a path inside your `Storage`, leave it empty for an in-memory database |
| `mysql` | MySQL, MariaDB |
| `pgsql` | PostgreSQL |

The component connects on construction, so `Database::getInstance()` is always usable. You can also
build one from an existing `PDO` connection, which is what the test suite does

```php
$database = Database::fromPDO($pdo, 'my-database');
```

## Raw queries

`query()` returns the rows, `exec()` returns the number of affected rows

```php
$rows = $database->query('SELECT id, name FROM product WHERE price_dollar > {}', [20]);
$count = $database->exec('DELETE FROM product WHERE name = {}', ['mouse']);
```

Values are **not** concatenated into the SQL : every `{}` placeholder is replaced, in order, by the
value prepared by your driver's builder.

| Value | Becomes |
|---|---|
| a string | an escaped, quoted literal |
| `null` | `NULL` |
| `true` / `false` | `TRUE` / `FALSE` |
| an array | a parenthesized list, each element quoted |
| an enum | its backing value |
| a `Model` | its primary key |

```php
$database->query('SELECT * FROM product WHERE id IN {}', [[1, 2, 3]]);
$database->query('SELECT * FROM product WHERE name LIKE {}', ['%scr%']);

// A placeholder already inside quotes is not quoted twice
$database->query("SELECT * FROM product WHERE name LIKE '%{}%'", ['scr']);
```

Escaping is delegated to the connection itself — `PDO::quote()` — so it follows the rules of the
server you are actually talking to, including its character set and settings like MySQL's
`NO_BACKSLASH_ESCAPES` or Postgres' `standard_conforming_strings`. A consequence worth knowing when
you compare generated SQL : the same value produces different text on different drivers.

```php
$database->build('SELECT {}', ["O'Brien"]);

// mysql  -> SELECT 'O\'Brien'
// pgsql  -> SELECT 'O''Brien'
// sqlite -> SELECT 'O''Brien'
```

Assert on what a query *returns*, not on the SQL it produced, or your tests will only pass on one
driver.

One case escaping does not cover : a placeholder outside quotes is always quoted, including a number.
`LIMIT {}` therefore produces `LIMIT '1'`, which MySQL rejects — write the bound directly, or pass it
through a validated integer of your own.

You can also read rows straight into a `Bunch`

```php
Bunch::fromQuery('SELECT id, name FROM product');

// The third argument selects the connection, the fourth keys the result by a column
Bunch::fromQuery('SELECT id, name FROM product', [], null, 'id');
```

## Inspecting the connection

```php
$database->getDriver();        // 'mysql', 'pgsql' or 'sqlite'
$database->getDatabase();      // database name (null for an in-memory SQLite)
$database->isConnected();
$database->lastInsertId();
$database->getLastStatement(); // the last PDOStatement
$database->hasTable('product');
$database->missingTable('product');
$database->hasField('product', 'name');
```

`build()` is the interpolation used by `query()` and `exec()`, exposed on its own when you only
want the resulting SQL

```php
$database->build('UPDATE product SET name = {}', ['Dale']); // UPDATE product SET name = 'Dale'
```

## Transactions

`transaction()` commits when your callback returns, rolls back when it throws, and **returns** the
`Throwable` instead of propagating it

```php
$error = $database->transaction(function (Database $database) {
    Product::insertArray(['name' => 'screen']);
    Product::insertArray(['name' => 'mouse']);
});

if ($error)
    return Response::internalServerError();
```

## Dry run

In dry-run mode, queries are built but never sent : `query()` returns an empty array and `exec()`
returns `0`. This is how migrations are checked before being applied

```php
$database->dryRun(function () {
    Product::delete()->where('name', 'screen')->fetch(); // built, not executed
});
```

## Building queries without a model

Every model method returning a query (`select()`, `insert()`, `update()`, `delete()`) gives you a
`Query`, and you can build one directly on a table

```php
$query = Query::select('product')
    ->selectField('name')
    ->selectExpression('COUNT(*)', 'total')
    ->where('price_dollar', 20, '>')
    ->order('name', 'ASC')
    ->limit(10, 20)
;

$query->build();  // the SQL string, for debugging
$query->fetch();  // execute
```

An aliased field or expression lands on each row under its alias (`$row->total` above), and
`limit(null, 20)` skips 20 rows without capping the result.

A `Query` is a tree of small objects (fields, conditions, joins, orders), and the SQL string only
exists when a builder renders it — that is why the same query works on MySQL, PostgreSQL and
SQLite.

### Conditions

```php
Product::select()
    ->where('name', 'screen')                  // name = 'screen'
    ->where('price_dollar', 20, '>')           // AND price_dollar > 20
    ->where('id', [1, 2, 3])                   // AND id IN (1,2,3)
    ->where('deleted_at', null)                // AND deleted_at IS NULL
    ->or()                                     // the next condition is joined with OR
    ->where('name', 'mouse')
    ->whereRaw('LENGTH(name) < 10')            // raw SQL, inserted as is
    ->when($request->param('cheap'), fn (Query $query) => $query->where('price_dollar', 10, '<'))
;
```

Conditions are joined with `AND` by default. `or()` changes the operator between the condition
before it and the one after it. `when()` only applies its callback when the condition is truthy,
which keeps optional filters out of `if` blocks.

When the conditions come as an associative array, `whereAssoc()` adds one `where()` per entry

```php
Product::select()->whereAssoc(['name' => 'screen', 'id' => [1, 2, 3]]); // name = 'screen' AND id IN (1,2,3)
```

It takes the same optional table as `where()`, and throws an `InvalidArgumentException` when given a
list.

### Joins

```php
Query::select('product')
    ->selectField('name', 'product')
    ->selectField('manager', 'product_manager')
    ->join('LEFT', 'product_manager', null, new FieldComparaison('product', 'id', '=', 'product_manager', 'product'))
;
```

Once a query has joins, `where()`, `order()` and `set()` need to know which table a column belongs
to : either pass the table as the last argument, or make sure the column was declared with
`selectField()` — otherwise the query throws `Could not determine a table for field [...]`.

### Executing

| Method | Returns |
|--------|---------|
| `fetch()` | an array of models (or `DummyModel` instances for a table query) |
| `fetchBunch()` / `toBunch()` | the same, wrapped in a `Bunch` |
| `first()` | the first model, or `null` |
| `count()` | the number of rows, by wrapping the query in a `SELECT COUNT(*)` |
| `build()` | the SQL string, without executing it |

`fetch()` is what runs the query, whatever its type — an `UPDATE`, `INSERT` or `DELETE` is executed
the same way

```php
Product::update()->where('name', 'screen')->set('name', 'monitor')->fetch();
Product::update()->where('id', 4)->setAssoc(['name' => 'monitor', 'price_dollar' => 150])->fetch();
Product::delete()->where('name', 'mouse')->limit(1)->fetch();
Product::insert()->insertField(['name'])->values(['screen'], ['mouse'])->fetch();
```

Every executing method takes an optional `Database`, so you can run the same query against another
connection — the global instance is used when you leave it out

```php
$products = Product::select()->fetch($otherDatabase);
$total = Product::select()->count($otherDatabase);
```

## Supporting another DBMS

A driver is one class extending `QueryBuilder`, declaring what it supports and rendering a `Query`

```php
class Oracle extends QueryBuilder
{
    public function supports(string $pdoDriver): bool
    {
        return 'oci' === $pdoDriver;
    }

    // build(), count(), transaction(), hasTable(), hasField()
}
```

Nothing else to register : the `Database` component asks every known builder which driver it
supports and takes the first match. The same applies to migration plans
(`Migration\Plan::support()`) and to model generation adapters
(`ModelGenerator\Adapters\DatabaseAdapter::supports()`), so a new DBMS means three classes and no
edit anywhere else.
<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./109-events.md">Previous : Events</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./111-models.md">Next : Models</a></div></td></tr></table>
