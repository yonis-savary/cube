<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./16-database-and-queries.md">Previous : Database and queries</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./18-models.md">Next : Models</a></div></td></tr></table>

# Migrations

A migration is one file describing a change to your schema, written against the `Plan` API so the
same file runs on SQLite, MySQL and PostgreSQL. The `MigrationManager` applies the files you have not
applied yet, in order, and remembers which ones ran.

```bash
php do make:migration CreateProductTable   # write a new migration file
php do migrate:migrate                     # apply what is pending, then regenerate the models
```

Here is the file you then fill, in `App/Migrations/`

```php
return new class extends Migration
{
    public function up(Plan $plan, Database $database)
    {
        $plan->create('product', [
            ModelField::id(),
            ModelField::timestamp('created_at'),
            ModelField::string('name', 200)->notNull()->unique(),
            ModelField::decimal('price_dollar', 10, 5)->nullable(),
        ]);

        $plan->create('product_manager', [
            ModelField::integer('product')->notNull()->references('product', 'id'),
            ModelField::string('manager', 200)->notNull(),
        ], 'UNIQUE (product, manager)'); // extra SQL, inserted inside CREATE TABLE ( ... )
    }

    public function down(Plan $plan, Database $database)
    {
        $plan->dropTable('product_manager');
        $plan->dropTable('product');
    }
};
```

## Creating a migration

```bash
php do make:migration AddAgencyTable
```

The name is the first argument ; without it, the command asks for one. When your project has several
applications, it asks which one receives the file. The file is written in the `Migrations/`
directory of that application, prefixed with the current date and time
(`2025_02_03_08_47_17_AddAgencyTable.php`), and returns an anonymous class extending `Migration`
with an empty `up()` and `down()`.

The migration files of every application are applied together, **sorted by file name** : the date
prefix is what keeps them in the order you wrote them.

## Applying migrations

```bash
php do migrate:migrate
php do migrate:migrate -s   # or --skip-generation
```

`migrate:migrate` applies every pending file, records each one in the migration table, then
regenerates your models with [`models:generate`](./18-models.md#generating-your-models) so your
classes never drift from your schema. `-s` / `--skip-generation` skips that last step. In production
it is always skipped : the models you deploy are the ones you generated and committed.

A file is known by its base name : renaming an applied migration makes it pending again. When one
migration fails, the command stops with a `FailedMigrationException` carrying the database's error
message, logs the error, and the following files are not applied. The ones applied before it stay
applied.

## How a migration runs

Each migration goes through two steps

1. **Dry run.** `up()` is called with a `DryRunPlan` while the `Database` is in
   [dry-run mode](./16-database-and-queries.md#dry-run), so nothing is sent. The plan checks your
   calls against the live schema and the tables created earlier in the same migration : dropping a
   table that does not exist, adding a column that already exists, altering, dropping or renaming a
   missing column, a foreign key pointing to a missing column, a unique index on a missing column,
   renaming a table onto an existing one. Any of those stops the migration before anything is applied.
2. **Transaction.** `up()` is called again with the plan of your driver, inside
   `Database::transaction()`. The migration is recorded as done only when it succeeds.

The `Database` given to `up()` is the real connection : use it for data you insert along with the
schema. Its queries are no-ops during the dry run

```php
public function up(Plan $plan, Database $database)
{
    $plan->create('user_type', [
        ModelField::id(),
        ModelField::string('label', 50)->notNull()->unique(),
    ]);

    $database->exec("INSERT INTO user_type (label) VALUES ('admin'), ('user'), ('guest')");
}
```

A migration runs in a transaction, but MySQL commits on every `CREATE`, `ALTER` and `DROP` : a
migration failing halfway leaves its first statements applied there. Keep one structural change per
migration on MySQL.

`down()` describes how to undo `up()`, but no command calls it yet : it is there for you to call by
hand, from a test for instance.

## The Plan API

| Method | Does |
|---|---|
| `create(string $table, array $fields = [], ?string $additionnalSQL = null)` | creates a table from a list of `ModelField` — an `id` column when the list is empty |
| `dropTable(string $table)` | drops a table |
| `renameTable(string $old, string $new)` | renames a table |
| `addColumn(string $table, ModelField $field)` | adds a column, and its foreign key when it has one |
| `alterColumn(string $table, string $column, ModelField $newProperties)` | changes a column's type, nullability, uniqueness, default — and its name when the field's differs |
| `renameField(string $table, string $old, string $new)` | renames a column |
| `dropColumn(string $table, string $column)` | drops a column |
| `addForeignKey(string $table, string $field, string $foreignTable, string $foreignKey, ?string $deleteBehavior = null)` | adds a foreign key, `$deleteBehavior` being `ModelField::ON_DELETE_CASCADE` or `ModelField::ON_DELETE_SET_NULL` |
| `addUniqueIndex(string $table, string\|array $fields)` | a unique index named `idx_<table>_<fields>` |
| `dropConstraint(string $table, string $constraintName)` | drops a named constraint |

On MySQL and PostgreSQL, `dropTable()`, `dropColumn()`, `dropConstraint()` and `alterColumn()` throw
a `RuntimeException` when the table or the column does not exist.

SQLite cannot change a column once it exists : on SQLite, `alterColumn()`, `addForeignKey()` and
`addUniqueIndex()` only log a warning, and `addColumn()` adds the column without its `unique()` or
foreign key constraint. Declare those in `create()`, or rebuild the table.

## Describing columns

Columns are the same `ModelField` objects your models use — see [Models](./18-models.md#fields)

```php
ModelField::id();                                   // integer, auto-increment, primary key, named "id"
ModelField::string('name', 255)->notNull();         // VARCHAR(255), TEXT without a length
ModelField::integer('stock')->default(0);
ModelField::float('weight');
ModelField::boolean('active')->default(true);
ModelField::decimal('price', 10, 2);                // DECIMAL(10,2), (10,5) when left out
ModelField::date('released_on');
ModelField::datetime('created_at');
ModelField::timestamp('updated_at');

ModelField::string('email')->unique();
ModelField::integer('agency_id')
    ->references('agency', 'id')                    // a table name, or a model class (Agency::class)
    ->onDeleteCascade();                            // or ->onDeleteSetNull()
```

A field is nullable unless you call `notNull()` (a primary key always is). `references()` takes a
table name or a model class : a model class is replaced by its `table()` in the `REFERENCES` clause.
The dry run checks that the referenced column exists in the database or in the migration. When a
model class's table does not exist yet, it checks the model's `fields()` instead : MySQL and
PostgreSQL still refuse the foreign key at execution if the table is missing, only SQLite accepts it.

| `ModelField` type | MySQL | PostgreSQL | SQLite |
|---|---|---|---|
| `string` | `VARCHAR(n)` / `TEXT` | `VARCHAR(n)` / `TEXT` | `VARCHAR(n)` / `TEXT` |
| `integer` | `INTEGER` | `INTEGER` (`SERIAL` for an auto-increment key) | `INTEGER` |
| `float` | `FLOAT` | `FLOAT` | `FLOAT` |
| `boolean` | `BOOLEAN` | `BOOLEAN` | `BOOLEAN` |
| `decimal` | `DECIMAL(m,d)` | `DECIMAL(m,d)` | `DECIMAL(m,d)` |
| `date` | `DATE` | `DATE` | `DATE` |
| `datetime` | `DATETIME` | `TIMESTAMP` | `DATETIME` |
| `timestamp` | `TIMESTAMP` | `TIMESTAMP` | `TIMESTAMP` |

## Raw SQL migrations

When the `Plan` cannot express a change, return a `RawSQLMigration` holding the SQL itself

```php
return new RawSQLMigration(
    "CREATE TABLE file (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(200) NOT NULL,
        path VARCHAR(255) NOT NULL
    )",
    "DROP TABLE file" // the down script, optional
);
```

The script goes through `Database::exec()` as is, so it is written for one DBMS only. A MySQL
connection refuses several statements in one call : keep one statement per `RawSQLMigration` there.
The dry run cannot check raw SQL, it only skips it.

## Configuration

`MigrationManagerConfiguration` in your `cube.php` renames the table or the directory

```php
new MigrationManagerConfiguration(
    tableName: '__cube_migration',
    directoryName: 'Migrations',
)
```

| Option | Default | Does |
|---|---|---|
| `tableName` | `__cube_migration` | the table recording applied migrations (name, date) |
| `directoryName` | `Migrations` | the directory, inside each application, holding migration files |

## Commands

| Command | Does |
|---|---|
| `make:migration [name]` | writes an empty migration file in the chosen application |
| `migrate:migrate [-s\|--skip-generation]` | applies pending migrations, then runs `models:generate` outside production |

<!-- menu --><table style='width:100%'><tr><td style='width: 33%'><div style="text-align: left"><a href="./16-database-and-queries.md">Previous : Database and queries</a></div></td><td style='width: 33%; text-align: center'><div style="Center"><a href="./README.md"> Readme</a></div></td><td style='width: 33%'><div style="text-align: right"><a href="./18-models.md">Next : Models</a></div></td></tr></table>
