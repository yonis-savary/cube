<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a migration altering an existing table must give on every DBMS : the table keeps its rows,
 * its indexes, its constraints and the foreign keys pointing at it, and the new definition is
 * enforced. MySQL and PostgreSQL alter in place, SQLite has to rebuild the table to get there.
 *
 * @internal
 */
class SchemaAlterationTest extends TestCase
{
    use TestMultipleDrivers;

    /** @param \Closure(Plan,Database):mixed $up */
    protected function migrate(Database $database, \Closure $up): ?\Throwable
    {
        $migration = new class($up) extends Migration {
            public function __construct(private \Closure $step) {}

            public function up(Plan $plan, Database $database)
            {
                ($this->step)($plan, $database);
            }
        };

        return $migration->execute(Plan::forDatabase($database), $database);
    }

    /** @param \Closure(Plan,Database):mixed $up */
    protected function assertMigrates(Database $database, \Closure $up): void
    {
        $thrown = $this->migrate($database, $up);
        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');
    }

    /** @param \Closure(Plan,Database):mixed $up */
    protected function assertMigrationFails(Database $database, \Closure $up): void
    {
        $this->assertNotNull($this->migrate($database, $up), 'The migration was expected to fail');
    }

    protected function assertRejected(Database $database, string $query): void
    {
        try {
            $database->exec($query);
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->fail("The database accepted [{$query}]");
    }

    protected function createArticles(Database $database): void
    {
        $this->assertMigrates($database, fn (Plan $plan) => $plan->create('article', [
            ModelField::id(),
            ModelField::string('label', 50),
            ModelField::decimal('price', 10, 2),
            ModelField::integer('stock'),
        ]));

        $database->exec("INSERT INTO article (label, price, stock) VALUES ('Keyboard', 49.90, 10), ('Mouse', 19.50, NULL)");
    }

    protected function createCustomersAndOrders(Database $database): void
    {
        $this->assertMigrates($database, function (Plan $plan) {
            $plan->create('customer', [
                ModelField::id(),
                ModelField::string('name', 50),
            ]);
            $plan->create('customer_order', [
                ModelField::id(),
                ModelField::integer('customer')->references('customer', 'id')->notNull()->onDeleteCascade(),
                ModelField::string('reference', 50),
            ]);
        });

        $database->exec("INSERT INTO customer (name) VALUES ('Alice'), ('Bob')");
        $database->exec("INSERT INTO customer_order (customer, reference) VALUES (1, 'A-1'), (1, 'A-2'), (2, 'B-1')");
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsTheRows(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 200)));

        $rows = $database->query('SELECT id, label, price, stock FROM article ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertEquals([1, 'Keyboard', 49.90, 10], [$rows[0]['id'], $rows[0]['label'], (float) $rows[0]['price'], $rows[0]['stock']]);
        $this->assertEquals([2, 'Mouse', 19.50, null], [$rows[1]['id'], $rows[1]['label'], (float) $rows[1]['price'], $rows[1]['stock']]);
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnToNotNullIsEnforced(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 50)->notNull()));

        $this->assertRejected($database, 'INSERT INTO article (label, price, stock) VALUES (NULL, 1, 1)');
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnToNotNullFailsOnANullRowAndLeavesTheTableIntact(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrationFails($database, fn (Plan $plan) => $plan->alterColumn('article', 'stock', ModelField::integer('stock')->notNull()));

        $this->assertCount(2, $database->query('SELECT id FROM article'));
        $database->exec("INSERT INTO article (label, price, stock) VALUES ('Screen', 120, NULL)");
        $this->assertCount(3, $database->query('SELECT id FROM article'));
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnToNullable(Database $database)
    {
        $this->assertMigrates($database, fn (Plan $plan) => $plan->create('article', [
            ModelField::id(),
            ModelField::string('label', 50)->notNull(),
        ]));

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 50)->nullable()));

        $database->exec('INSERT INTO article (label) VALUES (NULL)');
        $this->assertNull($database->query('SELECT label FROM article')[0]['label']);
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnSetsADefault(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'stock', ModelField::integer('stock')->default(5)));

        $database->exec("INSERT INTO article (label, price) VALUES ('Screen', 120)");
        $this->assertEquals(5, $database->query("SELECT stock FROM article WHERE label = 'Screen'")[0]['stock']);
    }

    /** A default holding the characters a definition is split on : commas, parentheses, quotes */
    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsADefaultHoldingSeparators(Database $database)
    {
        $this->assertMigrates($database, fn (Plan $plan) => $plan->create('setting', [
            ModelField::id(),
            ModelField::string('value', 100)->default("a, (b), 'c'"),
            ModelField::string('label', 50),
        ]));

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('setting', 'label', ModelField::string('label', 100)));

        $database->exec("INSERT INTO setting (label) VALUES ('x')");
        $this->assertEquals("a, (b), 'c'", $database->query('SELECT value FROM setting')[0]['value']);
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnToUniqueIsEnforced(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 50)->unique()));

        $this->assertRejected($database, "INSERT INTO article (label, price, stock) VALUES ('Mouse', 1, 1)");
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnToUniqueFailsOnDuplicates(Database $database)
    {
        $this->createArticles($database);
        $database->exec("INSERT INTO article (label, price, stock) VALUES ('Mouse', 25, 3)");

        $this->assertMigrationFails($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 50)->unique()));

        $this->assertCount(3, $database->query('SELECT id FROM article'));
    }

    /** MySQL's MODIFY and PostgreSQL's ALTER COLUMN leave the existing indexes alone */
    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsAnExistingUniqueConstraint(Database $database)
    {
        $this->assertMigrates($database, fn (Plan $plan) => $plan->create('coupon', [
            ModelField::id(),
            ModelField::string('code', 50)->unique(),
        ]));
        $database->exec("INSERT INTO coupon (code) VALUES ('WELCOME')");

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('coupon', 'code', ModelField::string('code', 100)));

        $this->assertRejected($database, "INSERT INTO coupon (code) VALUES ('WELCOME')");
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsTheIndexesOfTheTable(Database $database)
    {
        $this->assertMigrates($database, function (Plan $plan) {
            $plan->create('user_agency', [
                ModelField::id(),
                ModelField::integer('app_user'),
                ModelField::integer('agency'),
                ModelField::string('role', 20),
            ]);
            $plan->addUniqueIndex('user_agency', ['app_user', 'agency']);
        });
        $database->exec("INSERT INTO user_agency (app_user, agency, role) VALUES (1, 1, 'admin')");

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('user_agency', 'role', ModelField::string('role', 50)));
        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('user_agency', 'agency', ModelField::integer('agency')->notNull()));

        $this->assertRejected($database, "INSERT INTO user_agency (app_user, agency, role) VALUES (1, 1, 'viewer')");
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnRenamesTheColumnAndKeepsItsValues(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('name', 100)->notNull()));

        $this->assertTrue($database->hasField('article', 'name'));
        $this->assertFalse($database->hasField('article', 'label'));
        $this->assertEquals(['Keyboard', 'Mouse'], array_column($database->query('SELECT name FROM article ORDER BY id'), 'name'));
        $this->assertRejected($database, 'INSERT INTO article (name, price, stock) VALUES (NULL, 1, 1)');
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsTheChecksOfTheTable(Database $database)
    {
        $this->assertMigrates($database, fn (Plan $plan) => $plan->create(
            'article',
            [ModelField::integer('stock'), ModelField::string('label', 50)],
            'CHECK (stock >= 0)' // MySQL and PostgreSQL create the table with its first column only, then add the others
        ));

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 100)));

        $this->assertRejected($database, "INSERT INTO article (stock, label) VALUES (-1, 'Broken')");
    }

    #[DataProvider('getDatabases')]
    public function testAutoIncrementDoesNotReuseAnIdAfterAnAlteration(Database $database)
    {
        $this->createArticles($database);
        $database->exec("INSERT INTO article (label, price, stock) VALUES ('Screen', 120, 1)");
        $database->exec('DELETE FROM article WHERE id = 3');

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 200)));

        $database->exec("INSERT INTO article (label, price, stock) VALUES ('Webcam', 60, 1)");
        $this->assertEquals(4, $database->query("SELECT id FROM article WHERE label = 'Webcam'")[0]['id']);
    }

    /** Rebuilding a parent table must neither cascade its deletion to the children nor detach them */
    #[DataProvider('getDatabases')]
    public function testAlterColumnOfAReferencedTableKeepsTheChildRows(Database $database)
    {
        $this->createCustomersAndOrders($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)->notNull()));

        $this->assertCount(3, $database->query('SELECT id FROM customer_order'));

        $database->exec('DELETE FROM customer WHERE id = 1');
        $this->assertEquals([3], array_column($database->query('SELECT id FROM customer_order'), 'id'));
        $this->assertRejected($database, "INSERT INTO customer_order (customer, reference) VALUES (99, 'Z-1')");
    }

    /** Same thing when the plan is driven outside of a migration, hence outside of any transaction */
    #[DataProvider('getDatabases')]
    public function testAlterColumnOutsideOfAMigrationKeepsTheChildRows(Database $database)
    {
        $this->createCustomersAndOrders($database);

        Plan::forDatabase($database)->alterColumn('customer', 'name', ModelField::string('name', 200));

        $this->assertCount(3, $database->query('SELECT id FROM customer_order'));
        $this->assertRejected($database, "INSERT INTO customer_order (customer, reference) VALUES (99, 'Z-1')");
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsTheForeignKeysOfTheTable(Database $database)
    {
        $this->createCustomersAndOrders($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('customer_order', 'reference', ModelField::string('reference', 100)));

        $this->assertRejected($database, "INSERT INTO customer_order (customer, reference) VALUES (99, 'Z-1')");
        $database->exec('DELETE FROM customer WHERE id = 2');
        $this->assertCount(2, $database->query('SELECT id FROM customer_order'));
    }

    /** MySQL's MODIFY keeps the foreign key of the column, even when the new field does not repeat it */
    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsTheForeignKeyOfTheAlteredColumn(Database $database)
    {
        $this->createCustomersAndOrders($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('customer_order', 'customer', ModelField::integer('customer')->nullable()));

        $this->assertRejected($database, "INSERT INTO customer_order (customer, reference) VALUES (99, 'Z-1')");
        $database->exec("INSERT INTO customer_order (customer, reference) VALUES (NULL, 'N-1')");
        $this->assertCount(4, $database->query('SELECT id FROM customer_order'));
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnKeepsTheViewsReadingTheTable(Database $database)
    {
        $this->createArticles($database);
        $database->exec('CREATE VIEW article_price AS SELECT id, price FROM article');

        $this->assertMigrates($database, fn (Plan $plan) => $plan->alterColumn('article', 'label', ModelField::string('label', 200)));

        $this->assertCount(2, $database->query('SELECT id, price FROM article_price'));
    }

    #[DataProvider('getDatabases')]
    public function testSeveralAlterationsInOneMigration(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, function (Plan $plan) {
            $plan->alterColumn('article', 'label', ModelField::string('label', 200)->notNull());
            $plan->alterColumn('article', 'stock', ModelField::integer('stock')->default(0));
            $plan->addColumn('article', ModelField::string('sku', 20)->unique());
        });

        $database->exec("INSERT INTO article (label, price, sku) VALUES ('Screen', 120, 'SCR')");
        $this->assertEquals(0, $database->query("SELECT stock FROM article WHERE sku = 'SCR'")[0]['stock']);
        $this->assertRejected($database, "INSERT INTO article (label, price, sku) VALUES ('Other', 1, 'SCR')");
        $this->assertCount(3, $database->query('SELECT id FROM article'));
    }

    #[DataProvider('getDatabases')]
    public function testAlterColumnOfAMissingColumnFails(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrationFails($database, fn (Plan $plan) => $plan->alterColumn('article', 'color', ModelField::string('color', 20)));
    }

    #[DataProvider('getDatabases')]
    public function testAddUniqueColumnToAFilledTable(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->addColumn('article', ModelField::string('sku', 20)->unique()));

        $database->exec("INSERT INTO article (label, price, stock, sku) VALUES ('Screen', 120, 1, 'SCR')");
        $this->assertRejected($database, "INSERT INTO article (label, price, stock, sku) VALUES ('Other', 1, 1, 'SCR')");
        $this->assertCount(3, $database->query('SELECT id FROM article'));
    }

    #[DataProvider('getDatabases')]
    public function testAddNotNullColumnWithADefaultFillsTheRows(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->addColumn('article', ModelField::boolean('active')->notNull()->default(true)));

        $this->assertEquals([1, 1], array_map(intval(...), array_column($database->query('SELECT active FROM article'), 'active')));
    }

    #[DataProvider('getDatabases')]
    public function testAddColumnWithADefaultExpressionToAFilledTable(Database $database)
    {
        $this->createArticles($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->addColumn('article', ModelField::timestamp('created_at')->defaultExpression('CURRENT_TIMESTAMP')));

        $database->exec("INSERT INTO article (label, price, stock) VALUES ('Screen', 120, 1)");
        $this->assertNotNull($database->query("SELECT created_at FROM article WHERE label = 'Screen'")[0]['created_at']);
        $this->assertCount(3, $database->query('SELECT id FROM article'));
    }

    #[DataProvider('getDatabases')]
    public function testAddColumnWithAForeignKeyIsEnforced(Database $database)
    {
        $this->createCustomersAndOrders($database);

        $this->assertMigrates($database, function (Plan $plan) {
            $plan->create('agency', [ModelField::id()]);
            $plan->addColumn('customer', ModelField::integer('agency')->references('agency', 'id')->onDeleteSetNull());
        });
        $database->exec('INSERT INTO agency (id) VALUES (1)');
        $database->exec('UPDATE customer SET agency = 1 WHERE id = 1');

        $this->assertRejected($database, "INSERT INTO customer (name, agency) VALUES ('Carol', 99)");

        $database->exec('DELETE FROM agency WHERE id = 1');
        $this->assertNull($database->query('SELECT agency FROM customer WHERE id = 1')[0]['agency']);
        $this->assertCount(3, $database->query('SELECT id FROM customer_order'));
    }

    #[DataProvider('getDatabases')]
    public function testAddNotNullForeignKeyColumnWithADefaultToAFilledTable(Database $database)
    {
        $this->createCustomersAndOrders($database);

        $this->assertMigrates($database, fn (Plan $plan) => $plan->create('agency', [ModelField::id()]));
        $database->exec('INSERT INTO agency (id) VALUES (1)');

        $this->assertMigrates($database, fn (Plan $plan) => $plan->addColumn(
            'customer',
            ModelField::integer('agency')->references('agency', 'id')->notNull()->default(1)
        ));

        $this->assertEquals([1, 1], array_map(intval(...), array_column($database->query('SELECT agency FROM customer'), 'agency')));
        $this->assertRejected($database, "INSERT INTO customer (name, agency) VALUES ('Carol', 99)");
    }

    #[DataProvider('getDatabases')]
    public function testAddForeignKeyIsEnforced(Database $database)
    {
        $this->assertMigrates($database, function (Plan $plan) {
            $plan->create('customer', [ModelField::id()]);
            $plan->create('invoice', [ModelField::id(), ModelField::integer('customer')]);
        });
        $database->exec('INSERT INTO customer (id) VALUES (1)');
        $database->exec('INSERT INTO invoice (id, customer) VALUES (1, 1)');

        $this->assertMigrates($database, fn (Plan $plan) => $plan->addForeignKey('invoice', 'customer', 'customer', 'id', ModelField::ON_DELETE_CASCADE));

        $this->assertRejected($database, 'INSERT INTO invoice (id, customer) VALUES (2, 99)');
        $database->exec('DELETE FROM customer WHERE id = 1');
        $this->assertCount(0, $database->query('SELECT id FROM invoice'));
    }

    #[DataProvider('getDatabases')]
    public function testAddForeignKeyFailsOnAnOrphanRowAndLeavesTheTableIntact(Database $database)
    {
        $this->assertMigrates($database, function (Plan $plan) {
            $plan->create('customer', [ModelField::id()]);
            $plan->create('invoice', [ModelField::id(), ModelField::integer('customer')]);
        });
        $database->exec('INSERT INTO customer (id) VALUES (1)');
        $database->exec('INSERT INTO invoice (id, customer) VALUES (1, 1), (2, 99)');

        $this->assertMigrationFails($database, fn (Plan $plan) => $plan->addForeignKey('invoice', 'customer', 'customer', 'id'));

        $this->assertCount(2, $database->query('SELECT id FROM invoice'));
        $database->exec('INSERT INTO invoice (id, customer) VALUES (3, 42)');
    }

    #[DataProvider('getDatabases')]
    public function testDropUniqueColumn(Database $database)
    {
        $this->assertMigrates($database, fn (Plan $plan) => $plan->create('coupon', [
            ModelField::id(),
            ModelField::string('code', 50)->unique(),
            ModelField::integer('amount'),
        ]));
        $database->exec("INSERT INTO coupon (code, amount) VALUES ('WELCOME', 10)");

        $this->assertMigrates($database, fn (Plan $plan) => $plan->dropColumn('coupon', 'code'));

        $this->assertFalse($database->hasField('coupon', 'code'));
        $this->assertEquals(10, $database->query('SELECT amount FROM coupon')[0]['amount']);
    }

    #[DataProvider('getDatabases')]
    public function testDropIndexedColumn(Database $database)
    {
        $this->assertMigrates($database, function (Plan $plan) {
            $plan->create('coupon', [
                ModelField::id(),
                ModelField::string('code', 50),
                ModelField::integer('amount'),
            ]);
            $plan->addUniqueIndex('coupon', 'code');
        });
        $database->exec("INSERT INTO coupon (code, amount) VALUES ('WELCOME', 10)");

        $this->assertMigrates($database, fn (Plan $plan) => $plan->dropColumn('coupon', 'code'));

        $this->assertFalse($database->hasField('coupon', 'code'));
        $this->assertEquals(10, $database->query('SELECT amount FROM coupon')[0]['amount']);
    }
}
