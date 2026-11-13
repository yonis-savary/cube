<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Models\ModelField;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use PHPUnit\Framework\TestCase;

/**
 * SQLite cannot alter a column, add a foreign key or drop a constrained column in place : its plan
 * rebuilds the table. These tests hold what the rebuild must preserve that a `ModelField` cannot
 * describe (tables are created in raw SQL for that reason), and the connection state it must leave.
 *
 * @internal
 */
class SQLiteTableRebuildTest extends TestCase
{
    protected Database $database;

    protected function setUp(): void
    {
        $this->database = (new SQLiteProvider())->getEmptyDatabase();
    }

    /** @param \Closure(Plan,Database):mixed $up */
    protected function migrate(\Closure $up): ?\Throwable
    {
        $migration = new class($up) extends Migration {
            public function __construct(private \Closure $step) {}

            public function up(Plan $plan, Database $database)
            {
                ($this->step)($plan, $database);
            }
        };

        return $migration->execute(Plan::forDatabase($this->database), $this->database);
    }

    /** @param \Closure(Plan,Database):mixed $up */
    protected function assertMigrates(\Closure $up): void
    {
        $thrown = $this->migrate($up);
        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');
    }

    protected function assertRejected(string $query): void
    {
        try {
            $this->database->exec($query);
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->fail("The database accepted [{$query}]");
    }

    /** @return array<string,string> every schema object, as "type name" => its SQL */
    protected function getSchema(): array
    {
        $schema = [];
        foreach ($this->database->query("SELECT type, name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type, name") as $row)
            $schema["{$row['type']} {$row['name']}"] = $row['sql'];

        return $schema;
    }

    protected function getTableSQL(string $table): string
    {
        return $this->database->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = {}", [$table])[0]['sql'];
    }

    protected function areForeignKeysEnabled(): bool
    {
        return 1 === (int) $this->database->query('PRAGMA foreign_keys')[0]['foreign_keys'];
    }

    protected function createCustomersAndOrders(): void
    {
        $this->database->exec('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50))');
        $this->database->exec('CREATE TABLE customer_order (id INTEGER PRIMARY KEY AUTOINCREMENT, customer INTEGER NOT NULL REFERENCES customer(id) ON DELETE CASCADE)');
        $this->database->exec("INSERT INTO customer (id, name) VALUES (1, 'Alice'), (2, 'Bob')");
        $this->database->exec('INSERT INTO customer_order (customer) VALUES (1), (1), (2)');
    }

    public function testRebuildKeepsWhatAModelFieldCannotDescribe()
    {
        $this->database->exec('CREATE TABLE country (code TEXT PRIMARY KEY)');
        $this->database->exec(<<<'SQL'
            CREATE TABLE invoice_line (
                id INTEGER PRIMARY KEY AUTOINCREMENT, -- the line, a comma, a (parenthesis)
                label TEXT COLLATE NOCASE,
                unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
                quantity INTEGER NOT NULL DEFAULT (1 + 0),
                total DECIMAL(10, 2) GENERATED ALWAYS AS (unit_price * quantity) STORED,
                country TEXT,
                note VARCHAR(20),
                /* a block comment, with a comma */
                CONSTRAINT positive_quantity CHECK (quantity > 0),
                CONSTRAINT line_country FOREIGN KEY (country) REFERENCES country(code) ON UPDATE CASCADE
            )
            SQL);
        $this->database->exec("INSERT INTO country (code) VALUES ('FR')");
        $this->database->exec(<<<'SQL'
            INSERT INTO invoice_line (label, unit_price, quantity, country, note) VALUES ('keyboard', 10, 3, 'FR', 'x')
            SQL);

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('invoice_line', 'note', ModelField::string('note', 200)->notNull()->default('')));

        $row = $this->database->query(<<<'SQL'
            SELECT label, unit_price AS price, quantity, total, country, note FROM invoice_line WHERE label = 'KEYBOARD'
            SQL);
        $this->assertCount(1, $row, 'COLLATE NOCASE was lost');
        $this->assertEquals([10, 3, 30, 'FR', 'x'], [(float) $row[0]['price'], $row[0]['quantity'], (float) $row[0]['total'], $row[0]['country'], $row[0]['note']]);

        $this->assertRejected('INSERT INTO invoice_line (label, quantity) VALUES (\'mouse\', 0)');
        $this->assertRejected('INSERT INTO invoice_line (label, country) VALUES (\'mouse\', \'XX\')');

        $this->database->exec("UPDATE country SET code = 'FRA' WHERE code = 'FR'");
        $this->assertEquals('FRA', $this->database->query('SELECT country FROM invoice_line')[0]['country'], 'ON UPDATE CASCADE was lost');

        $this->database->exec('INSERT INTO invoice_line (label) VALUES (\'mouse\')');
        $this->assertEquals(1, $this->database->query('SELECT quantity FROM invoice_line WHERE label = \'mouse\'')[0]['quantity']);
    }

    public function testRebuildOnlyChangesTheAlteredColumnDefinition()
    {
        $this->database->exec('CREATE TABLE article (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT COLLATE NOCASE, stock INTEGER)');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('article', 'stock', ModelField::integer('stock')->notNull()->default(0)));

        $sql = $this->getTableSQL('article');
        $this->assertStringContainsString('label TEXT COLLATE NOCASE', $sql);
        $this->assertStringContainsString('id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    }

    public function testRebuildKeepsTableOptions()
    {
        $this->database->exec('CREATE TABLE setting (name TEXT PRIMARY KEY, value TEXT) WITHOUT ROWID');
        $this->database->exec("INSERT INTO setting (name, value) VALUES ('theme', 'dark')");

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('setting', 'value', ModelField::string('value', 100)->notNull()));

        $this->assertStringEndsWith('WITHOUT ROWID', trim($this->getTableSQL('setting')));
        $this->assertEquals('dark', $this->database->query('SELECT value FROM setting')[0]['value']);
    }

    public function testRebuildKeepsACompositePrimaryKey()
    {
        $this->database->exec('CREATE TABLE user_agency (app_user INTEGER, agency INTEGER, role TEXT, PRIMARY KEY (app_user, agency))');
        $this->database->exec("INSERT INTO user_agency VALUES (1, 1, 'admin')");

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('user_agency', 'role', ModelField::string('role', 20)->notNull()));

        $this->assertRejected("INSERT INTO user_agency VALUES (1, 1, 'viewer')");
    }

    public function testRebuildKeepsTheTriggersOfTheTable()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('CREATE TABLE customer_log (customer INTEGER)');
        $this->database->exec('CREATE TRIGGER log_customer AFTER INSERT ON customer BEGIN INSERT INTO customer_log VALUES (NEW.id); END');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)));

        $this->database->exec("INSERT INTO customer (name) VALUES ('Carol')");
        $this->assertCount(1, $this->database->query('SELECT customer FROM customer_log'));
    }

    public function testRebuildKeepsTheIndexesOfTheTable()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('CREATE INDEX idx_customer_name ON customer(name)');
        $this->database->exec('CREATE UNIQUE INDEX idx_customer_lower_name ON customer(lower(name)) WHERE name IS NOT NULL');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)));

        $this->assertArrayHasKey('index idx_customer_name', $this->getSchema());
        $this->assertRejected("INSERT INTO customer (name) VALUES ('ALICE')");
    }

    public function testRenamingAColumnCarriesItsIndexAlong()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('CREATE UNIQUE INDEX idx_customer_name ON customer(name)');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('full_name', 200)));

        $this->assertRejected("INSERT INTO customer (full_name) VALUES ('Alice')");
    }

    /** The rebuilt parent is renamed into place : the children must still reference its name */
    public function testRebuildLeavesTheOtherTablesUntouched()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('CREATE VIEW customer_name AS SELECT name FROM customer');
        $before = $this->getSchema();

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)));

        $after = $this->getSchema();
        $this->assertEquals($before['table customer_order'], $after['table customer_order']);
        $this->assertEquals($before['view customer_name'], $after['view customer_name']);
        $this->assertEquals(array_keys($before), array_keys($after), 'A temporary table was left behind, or an object was lost');
    }

    public function testAFailedRebuildIsRolledBack()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('INSERT INTO customer (name) VALUES (NULL)');
        $before = $this->getSchema();

        $this->assertNotNull($this->migrate(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)->notNull())));

        $this->assertEquals($before, $this->getSchema());
        $this->assertCount(3, $this->database->query('SELECT id FROM customer'));
        $this->assertCount(3, $this->database->query('SELECT id FROM customer_order'));
    }

    public function testAMigrationFailingAfterARebuildIsRolledBack()
    {
        $this->createCustomersAndOrders();
        $before = $this->getSchema();

        $thrown = $this->migrate(function (Plan $plan) {
            $plan->alterColumn('customer', 'name', ModelField::string('name', 200));
            $plan->alterColumn('customer', 'name', ModelField::string('name', 200)->notNull()->unique());
            $plan->addForeignKey('customer', 'name', 'customer_order', 'id');
        });

        $this->assertNotNull($thrown);
        $this->assertEquals($before, $this->getSchema());
        $this->assertCount(3, $this->database->query('SELECT id FROM customer_order'));
    }

    public function testForeignKeysAreEnforcedAgainAfterAMigration()
    {
        $this->createCustomersAndOrders();

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)));

        $this->assertTrue($this->areForeignKeysEnabled());
        $this->assertRejected('INSERT INTO customer_order (customer) VALUES (99)');
    }

    public function testForeignKeysAreEnforcedAgainAfterAFailedMigration()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('INSERT INTO customer (name) VALUES (NULL)');

        $this->assertNotNull($this->migrate(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)->notNull())));

        $this->assertTrue($this->areForeignKeysEnabled());
    }

    public function testForeignKeysAreEnforcedAgainAfterARebuildOutsideOfAMigration()
    {
        $this->createCustomersAndOrders();

        Plan::forDatabase($this->database)->alterColumn('customer', 'name', ModelField::string('name', 200));

        $this->assertTrue($this->areForeignKeysEnabled());
        $this->assertCount(3, $this->database->query('SELECT id FROM customer_order'));
    }

    /** A connection opened without foreign keys must not get them switched on by a migration */
    public function testRebuildRestoresDisabledForeignKeys()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('PRAGMA foreign_keys = OFF');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)));

        $this->assertFalse($this->areForeignKeysEnabled());
    }

    public function testRebuildKeepsTheAutoIncrementSequence()
    {
        $this->createCustomersAndOrders();
        $this->database->exec("INSERT INTO customer (id, name) VALUES (10, 'Zed')");
        $this->database->exec('DELETE FROM customer WHERE id = 10');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('customer', 'name', ModelField::string('name', 200)));

        $this->database->exec("INSERT INTO customer (name) VALUES ('Carol')");
        $this->assertEquals(11, $this->database->query("SELECT id FROM customer WHERE name = 'Carol'")[0]['id']);
    }

    public function testRebuildWorksWithReservedWords()
    {
        $this->database->exec('CREATE TABLE "order" ("group" TEXT, "select" INTEGER)');
        $this->database->exec('INSERT INTO "order" VALUES (\'a\', 1)');

        $this->assertMigrates(fn (Plan $plan) => $plan->alterColumn('order', 'group', ModelField::string('group', 20)->notNull()));

        $this->assertEquals(['a', 1], array_values($this->database->query('SELECT "group", "select" FROM "order"')[0]));
    }

    /** SQLite refuses `ADD COLUMN` for these definitions on a filled table : the plan must rebuild instead */
    public function testAddColumnFallsBackToARebuildWhenSQLiteRefusesIt()
    {
        $this->createCustomersAndOrders();

        $this->assertMigrates(function (Plan $plan) {
            $plan->addColumn('customer', ModelField::string('email', 100)->unique());
            $plan->addColumn('customer', ModelField::timestamp('created_at')->notNull()->defaultExpression('CURRENT_TIMESTAMP'));
            $plan->addColumn('customer', ModelField::integer('sponsor')->references('customer', 'id')->notNull()->default(1));
        });

        $rows = $this->database->query('SELECT email, created_at, sponsor FROM customer ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertNull($rows[0]['email']);
        $this->assertNotNull($rows[0]['created_at']);
        $this->assertEquals(1, $rows[1]['sponsor']);
        $this->assertCount(3, $this->database->query('SELECT id FROM customer_order'));
    }

    public function testDropColumnUsedByAnIndexDropsTheIndex()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('CREATE INDEX idx_customer_name ON customer(name)');

        $this->assertMigrates(fn (Plan $plan) => $plan->dropColumn('customer', 'name'));

        $this->assertArrayNotHasKey('index idx_customer_name', $this->getSchema());
        $this->assertCount(3, $this->database->query('SELECT id FROM customer_order'));
    }

    public function testDropConstraintRemovesANamedTableConstraint()
    {
        $this->database->exec('CREATE TABLE article (id INTEGER PRIMARY KEY, stock INTEGER, CONSTRAINT positive_stock CHECK (stock >= 0))');
        $this->database->exec('INSERT INTO article (stock) VALUES (1)');

        $this->assertMigrates(fn (Plan $plan) => $plan->dropConstraint('article', 'positive_stock'));

        $this->database->exec('INSERT INTO article (stock) VALUES (-1)');
        $this->assertCount(2, $this->database->query('SELECT id FROM article'));
    }

    public function testDropConstraintDropsAnIndex()
    {
        $this->assertMigrates(function (Plan $plan) {
            $plan->create('coupon', [ModelField::id(), ModelField::string('code', 50)]);
            $plan->addUniqueIndex('coupon', 'code');
            $plan->dropConstraint('coupon', 'idx_coupon_code');
        });

        $this->database->exec("INSERT INTO coupon (code) VALUES ('WELCOME'), ('WELCOME')");
        $this->assertCount(2, $this->database->query('SELECT id FROM coupon'));
    }

    public function testDropConstraintOfAnUnknownNameFails()
    {
        $this->createCustomersAndOrders();

        $this->assertNotNull($this->migrate(fn (Plan $plan) => $plan->dropConstraint('customer_order', 'customer_order_ibfk_1')));
    }

    /** No warning left : every operation the plan accepts is now applied */
    public function testRebuildingOperationsDoNotOnlyWarn()
    {
        $this->createCustomersAndOrders();
        $this->database->exec('CREATE TABLE invoice (id INTEGER PRIMARY KEY, customer INTEGER)');

        $this->assertMigrates(fn (Plan $plan) => $plan->addForeignKey('invoice', 'customer', 'customer', 'id'));

        $this->assertCount(1, $this->database->query("SELECT * FROM pragma_foreign_key_list('invoice')"));
    }
}
