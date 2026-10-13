<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./15-api-documentation.md">Previous : Api documentation</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./17-migrations.md">Next : Migrations</a></div></td></tr></table>

# Database and queries

The `Database` component owns your SQL connection : a thin layer above `PDO` that interpolates
values safely and hands every `Query` to the `QueryBuilder` matching your driver.

You can talk to it in two ways, and mix them freely

```php
// Raw SQL, with placeholders
Database::getInstance()->query('SELECT * FROM product WHERE name = {}', ['screen']);

// Query objects, built step by step
Product::select()->where('name', 'screen')->fetch();
```

The second form is mostly used through [Models](./18-models.md) ; this page describes the
connection and the query builder underneath. Changing the schema is covered by
[Migrations](./17-migrations.md).

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
| `sqlite` | SQLite — `database` is a path inside your `Storage`, an empty string gives an in-memory database |
| `mysql` | MySQL, MariaDB |
| `pgsql` | PostgreSQL |

Without any `DatabaseConfiguration`, Cube uses SQLite on `Storage/db.sqlite`. A SQLite connection
runs `PRAGMA foreign_keys = ON` when it opens, so its foreign keys are enforced.

The component connects on construction, so `Database::getInstance()` is always usable. You can also
build one from an existing `PDO` connection, which is what the test suite does

```php
$database = Database::fromPDO($pdo, 'my-database');
```

Every method that queries the database takes an optional `Database` as its last argument, and falls
back to the global instance — see [Components](./06-components.md) to swap that instance.

## Raw queries

`query()` returns the rows, `exec()` returns the number of affected rows

```php
$rows = $database->query('SELECT id, name FROM product WHERE price_dollar > {}', [20]);
$count = $database->exec('DELETE FROM product WHERE name = {}', ['mouse']);

// The third argument of query() is the PDO fetch mode, PDO::FETCH_ASSOC by default
$rows = $database->query('SELECT id, name FROM product', [], PDO::FETCH_NUM);
```

Values are **not** concatenated into the SQL : every `{}` placeholder is replaced, in order, by the
value prepared by your driver's builder. A placeholder without a matching value becomes `NULL`, and
without any value at all the query runs exactly as written, a `{}` included.

| Value | Becomes |
|---|---|
| a string or a number | an escaped, quoted literal |
| `null` | `NULL` |
| `true` / `false` | `'1'` / `'0'` |
| an array | a parenthesized list, each element quoted |
| an enum | its backing value |
| a `DateTimeInterface` | `'Y-m-d H:i:s'` |
| a `Model` | its primary key |

```php
$database->query('SELECT * FROM product WHERE id IN {}', [[1, 2, 3]]);
$database->query('SELECT * FROM product WHERE name LIKE {}', ['%scr%']);

// A placeholder already inside quotes is not quoted twice
$database->query("SELECT * FROM product WHERE name LIKE '%{}%'", ['scr']);

// Inside identifier quotes (` for MySQL and SQLite, " for Postgres and SQLite), the value is a name :
// the quote character is doubled, nothing else is touched
$database->query('SELECT COUNT(*) FROM `{}`', ['product']);
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

Assert on what a query *returns*, not on the SQL it produced, or your tests only pass on one
driver.

Booleans are quoted like any other value : MySQL holds an unquoted `FALSE` equal to any string
starting with a letter, so `where('token', false)` would have matched every token. A MySQL connection
also refuses several statements in one call (`SELECT 1; SELECT 2` throws a `PDOException`).
Both only hold for a connection Cube opens itself : a `PDO` you give to `fromPDO()` keeps its own
options, so build it with `charset=utf8mb4` in its DSN and `Pdo\Mysql::ATTR_MULTI_STATEMENTS => false`.
A MySQL connection using a multi-byte charset that defeats escaping (`gbk`, `big5`, `sjis`...) is
refused with an `InvalidArgumentException`.

One case escaping does not cover : a placeholder outside quotes is always quoted, a number included.
`LIMIT {}` therefore produces `LIMIT '1'`, which MySQL rejects — write the bound directly, or pass it
through a validated integer of your own.

You can also read rows straight into a [`Bunch`](./07-collections.md)

```php
Bunch::fromQuery('SELECT id, name FROM product');

// The third argument selects the connection, the fourth extracts one column from each row
Bunch::fromQuery('SELECT id FROM product', [], null, 'id'); // Bunch of ids
```

## Inspecting the connection

```php
$database->getDriver();        // 'mysql', 'pgsql' or 'sqlite'
$database->getDatabase();      // database name (null for an in-memory SQLite)
$database->getHost();
$database->getPort();
$database->getUser();
$database->getConnection();    // the underlying PDO
$database->isConnected();
$database->lastInsertId();
$database->getLastStatement(); // the last PDOStatement run by query()
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
returns `0`. The previous mode comes back when your callback ends, even when it throws, and the
callback's return value is returned. This is how migrations are checked before being applied

```php
$database->dryRun(function () {
    Product::delete()->where('name', 'screen')->fetch(); // built, not executed
});
```

## Building queries

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

A query on a table selects nothing until you call `selectField()` or `selectExpression()` : only a
model's `select()` lists its fields for you. An aliased field or expression lands on each row under
its alias (`$row->total` above).

| Method | Does |
|---|---|
| `selectField($field, $table = null, $alias = null)` | selects a column |
| `selectExpression($expression, $alias = null)` | selects raw SQL, inserted as is |
| `order($fieldOrAlias, $type = 'DESC', $table = null)` | sorts, **descending by default** |
| `limit($limit = null, $offset = null)` | caps and skips — `limit(null, 20)` skips 20 rows without a cap |
| `limitless()` | removes the limit |
| `set($field, $value, $table = null)` / `setAssoc([...])` | the columns of an `UPDATE` |
| `insertField([...])` / `values([...], [...])` | the columns and the rows of an `INSERT` |
| `join($type, $table, $alias = null, $condition = null)` | a join, see [Joins](#joins) |
| `with(...$relations)` | loads model relations, see [Models](./18-models.md#relations) |

A `Query` is a tree of small objects (fields, conditions, joins, orders), and the SQL string only
exists when a builder renders it — that is why the same query works on MySQL, PostgreSQL and
SQLite.

### Conditions

```php
Product::select()
    ->where('name', 'screen')                  // name = 'screen'
    ->where('price_dollar', 20, '>')           // AND price_dollar > 20
    ->where('id', [1, 2, 3])                   // AND id IN (1,2,3)
    ->where('id', [4, 5], '<>')                // AND id NOT IN (4,5)
    ->where('deleted_at', null)                // AND deleted_at IS NULL
    ->or()                                     // the next condition is joined with OR
    ->where('name', 'mouse')
    ->whereRaw('LENGTH(name) < 10')            // raw SQL, inserted as is
    ->when($request->param('cheap'), fn (Query $query) => $query->where('price_dollar', 10, '<'))
;
```

Conditions are joined with `AND` by default. `or()` changes the operator between the condition
before it and the one after it. `when()` only applies its callback when the condition is truthy —
and its optional third argument when it is not — which keeps optional filters out of `if` blocks.

An array or a `Bunch` turns `=` into `IN` and `<>` into `NOT IN`. An empty list matches nothing with
`IN` and everything with `NOT IN`, instead of producing invalid SQL. `null` turns `=` into `IS` and
`<>` into `IS NOT`.

`whereGroup()` puts the conditions its callback adds between parentheses, so an `or()` stays inside
them. Groups can be nested

```php
// WHERE active = '1' AND (name = 'screen' OR name = 'mouse')
Product::select()
    ->where('active', true)
    ->whereGroup(fn (Query $query) => $query
        ->where('name', 'screen')
        ->or()
        ->where('name', 'mouse')
    );
```

When the conditions come as an associative array, `whereAssoc()` adds one `where()` per entry

```php
Product::select()->whereAssoc(['name' => 'screen', 'id' => [1, 2, 3]]); // name = 'screen' AND id IN (1,2,3)
```

It takes the same optional table as `where()`, and throws an `InvalidArgumentException` when given a
list — so does `setAssoc()`.

### Subqueries

A `Query` given as a value is rendered as a subquery, in a condition as in a `set()`. `whereIn()`
and `whereNotIn()` are shortcuts taking an array, a `Bunch` or a `Query`

```php
$managedByAlice = Query::select('product_manager')
    ->selectField('product', 'product_manager')
    ->where('manager', 'alice', table: 'product_manager');

Product::select()->whereIn('id', $managedByAlice)->fetch();
Product::select()->whereNotIn('id', $managedByAlice)->fetch();
Product::select()->where('id', $managedByAlice)->fetch(); // id = (SELECT ...), for a single-row subquery
```

### What is escaped, and what is not

Only **values** are escaped. Field names, table names and operators go into the SQL as written, so
the query refuses anything else than a plain name : letters, digits, `_` and `$` for a column (not
starting with a digit), plus `&` and `.` for a table or an alias. An operator must be one of `=`,
`<>`, `!=`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE`, `ILIKE`, `NOT ILIKE`, `IN`, `NOT IN`, `IS`,
`IS NOT`, an order `ASC` or `DESC`, a join type `LEFT`, `RIGHT`, `INNER`, `CROSS`, `FULL` or their
`OUTER` forms. Anything else throws an `InvalidArgumentException`

```php
Product::select()->order('id, (SELECT 1) --'); // InvalidArgumentException
```

A `LIKE` condition always declares `\` as its escape character, on every driver. Escape a value you
search as written with `Query::escapeLike()`, so its `%` and `_` stop being wildcards

```php
Product::select()->where('name', '%'.Query::escapeLike($request->param('search')).'%', 'LIKE');
```

`whereRaw()` and `selectExpression()` are inserted as is, with no check at all. A valid name taken
from request input is still a column the client chose : check it against a fixed list first

```php
// ?sort=price_dollar — the column comes from the client, so it must be one the model declares
$column = $request->param('sort');
if (!array_key_exists($column, Product::fields()))
    return Response::badRequest('Unknown column');

Product::select()->order($column, 'ASC')->fetch();
```

### Joins

```php
Query::select('product')
    ->selectField('name', 'product')
    ->selectField('manager', 'product_manager')
    ->join('LEFT', 'product_manager', null, new FieldComparaison('product', 'id', '=', 'product_manager', 'product'))
;
```

`FieldComparaison` compares two columns : source table, source column, operator, target table,
target column.

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
| `exists()` | whether at least one row matches, leaving the query untouched |
| `chunk($size, $callback)` | calls `$callback(array $rows, Bunch $rows)` once per page of `$size` rows |
| `build()` | the SQL string, without executing it |

`fetch()` is what runs the query, whatever its type — an `UPDATE`, `INSERT` or `DELETE` is executed
the same way

```php
Product::update()->where('name', 'screen')->set('name', 'monitor')->fetch();
Product::update()->where('id', 4)->setAssoc(['name' => 'monitor', 'price_dollar' => 150])->fetch();
Product::delete()->where('name', 'mouse')->limit(1)->fetch();
Product::insert()->insertField(['name'])->values(['screen'], ['mouse'])->fetch();
```

`chunk()` walks a large table without loading it at once. It counts the rows first, then fetches
one page at a time, ignoring any limit you set before

```php
Product::select()->order('id', 'ASC')->chunk(500, function (array $products) {
    foreach ($products as $product)
        $this->reindex($product);
});
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
    public static function supports(string $pdoDriver): bool
    {
        return 'oci' === $pdoDriver;
    }

    // build(), count(), transaction(), hasTable(), hasField(), getIdentifierDelimiters()
}
```

Nothing else to register : the `Database` component asks every builder Cube discovers which PDO
driver it supports, and builds the first match — see
[Applications and discovery](./04-applications-and-discovery.md). The rest of the stack works the
same way, each part with a static `supports()` :

| To support | Extend |
|---|---|
| queries | `Cube\Data\Database\Builders\QueryBuilder` |
| migration plans | `Cube\Data\Database\Migration\Plan` |
| the migration history table | `Cube\Data\Database\MigrationManager` |
| model generation | `Cube\Data\Models\ModelGenerator\Adapters\DatabaseAdapter` |

A new DBMS is these four classes and no edit anywhere else. You can also give a `QueryBuilder`
instance to the `Database` constructor, which then skips the lookup.

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./15-api-documentation.md">Previous : Api documentation</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./17-migrations.md">Next : Migrations</a></div></td></tr></table>
