<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Database\Migration\Plans\Exceptions\DryRunPlanException;
use Cube\Data\Models\ModelField;
use Cube\Data\Models\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    use TestMultipleDrivers;
    protected function getDatabasePlan(Database $database): Plan
    {
        return Plan::forDatabase($database);
    }

    #[ DataProvider('getDatabases') ]
    public function testBasicCreation(Database $database)
    {
        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create("my_users", [
                    ModelField::id(),
                    ModelField::string("username", 50)->unique(),
                    ModelField::string("password", 150),
                    ModelField::string("bio"),
                    ModelField::timestamp("last_login")
                ]);
                $plan->create('user_log', [
                    ModelField::id(),
                    ModelField::integer('user_id')->references('my_users', 'id')->notNull()->onDeleteCascade(),
                    ModelField::datetime('log_datetime'),
                ]);
            }
        };

        $plan = $this->getDatabasePlan($database);
        $thrown = $migration->execute($plan, $database);
        
        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');

        $this->assertTrue($database->hasTable("my_users"));
        $this->assertTrue($database->hasField("my_users", "id"));
        $this->assertTrue($database->hasField("my_users", "username"));
        $this->assertTrue($database->hasField("my_users", "password"));
        $this->assertTrue($database->hasField("my_users", "bio"));
        $this->assertTrue($database->hasField("my_users", "last_login"));

        $this->assertTrue($database->hasTable("user_log"));
        $this->assertTrue($database->hasField("user_log", "id"));
        $this->assertTrue($database->hasField("user_log", "user_id"));
        $this->assertTrue($database->hasField("user_log", "log_datetime"));
    }

    /** A reference given as a Model class failed the dry run and was written as is in the REFERENCES clause. */
    #[ DataProvider('getDatabases') ]
    public function testAReferenceCanNameAModelClass(Database $database)
    {
        $reviewTarget = new class extends Model {
            public static function table(): string { return 'review_target'; }
            public static function fields(): array { return []; }
            public static function relations(): array { return []; }
        };

        $migration = new class($reviewTarget::class) extends Migration {
            public function __construct(private string $reviewTargetModel) {}

            public function up(Plan $plan, Database $database)
            {
                $plan->create('review_target', [ModelField::id()]);
                $plan->create('review', [
                    ModelField::id(),
                    ModelField::integer('target')->references($this->reviewTargetModel, 'id')->notNull(),
                ]);
            }
        };

        $thrown = $migration->execute($this->getDatabasePlan($database), $database);

        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');
        $this->assertTrue($database->hasField('review', 'target'));
    }

    /** create(), addColumn() and the rename methods wrote names between delimiters without doubling the delimiter itself. */
    #[ DataProvider('getDatabases') ]
    public function testNamesHoldingTheDelimiterAreEscaped(Database $database)
    {
        $delimiter = 'pgsql' === $database->getDriver() ? '"' : '`';
        $table = "odd{$delimiter}table";

        $plan = $this->getDatabasePlan($database);
        $plan->create($table, [ModelField::id()]);
        $plan->addColumn($table, ModelField::string("odd{$delimiter}column", 20));

        $this->assertTrue($database->hasTable($table));
        $this->assertTrue($database->hasField($table, "odd{$delimiter}column"));
        $this->assertFalse($database->hasTable('odd'));
    }

    #[ DataProvider('getDatabases') ]
    public function testSimpleDrop(Database $database) 
    {
        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create("my_users");
            }

            public function down(Plan $plan, Database $database)
            {
                $plan->dropTable("my_users");
            }
        };

        $plan = $this->getDatabasePlan($database);

        $migration->execute($plan, $database);
        $this->assertTrue($database->hasTable("my_users"));

        $migration->down($plan, $database);
        $this->assertFalse($database->hasTable("my_users"));
    }

    #[ DataProvider('getDatabases') ]
    public function testRenameTable(Database $database) 
    {
        $firstMigration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create("my_users");
            }
        };

        $secondMigration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->renameTable("my_users", "users_backup");
            }
        };

        $plan = $this->getDatabasePlan($database);

        $error = $firstMigration->execute($plan, $database);
        $this->assertNull($error);
        $this->assertTrue($database->hasTable("my_users"));
        $this->assertFalse($database->hasTable("users_backup"));

        $error = $secondMigration->execute($plan, $database);
        $this->assertNull($error);
        $this->assertFalse($database->hasTable("my_users"));
        $this->assertTrue($database->hasTable("users_backup"));
    }

    #[ DataProvider('getDatabases') ]
    public function testRenameField(Database $database) 
    {
        $firstMigration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create("my_users", [
                    ModelField::id(),
                    ModelField::string("username", 50)->unique(),
                ]);
            }
        };

        $secondMigration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->renameField("my_users", "username", "login");
            }
        };

        $plan = $this->getDatabasePlan($database);

        $firstMigration->execute($plan, $database);
        $this->assertTrue($database->hasField("my_users", "username"));
        $this->assertFalse($database->hasField("my_users", "login"));

        $secondMigration->execute($plan, $database);
        $this->assertFalse($database->hasField("my_users", "username"));
        $this->assertTrue($database->hasField("my_users", "login"));
    }


    /**
     * With some DBMS such as MySQL, we cannot put CREATE TABLE
     * statements in transaction and rollback them :(
     *
     * To improve safety, a DryRun migration plan was added to
     * perform some checks before actually running the migratin
     *
     * In this test, we make a migration that create a table (with no error)
     * and then try to rename something that does not exists
     */
    #[ DataProvider('getDatabases') ]
    public function testTransactionWorksWithCreateTable(Database $database) 
    {
        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create("should_not_be_created", [
                    ModelField::id()
                ]);

                $plan->renameTable("SomeThingThatDontExists", "SomeDummyName");
            }
        };
        $plan = $this->getDatabasePlan($database);

        $thrown = $migration->execute($plan, $database);

        $this->assertInstanceOf(DryRunPlanException::class, $thrown);
        $this->assertFalse($database->hasTable("should_not_be_created"));
    }

    #[ DataProvider('getDatabases') ]
    public function testAlterTable(Database $database) 
    {
        $firstMigration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create("order_detail", [
                    ModelField::id(),
                    ModelField::decimal('unit_price', 10, 5),
                    ModelField::integer('quantity')
                ]);
            }
        };

        $secondMigration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->alterColumn(
                    'order_detail',
                    'quantity',
                    ModelField::float('quantity')->notNull()->default(1)
                );
            }
        };

        $plan = $this->getDatabasePlan($database);

        $error = $firstMigration->execute($plan, $database);
        $this->assertNull($error);

        $error = $secondMigration->execute($plan, $database);
        $this->assertNull($error);
    }





    #[ DataProvider('getDatabases') ]
    public function testUniqueKeyConstraint(Database $database)
    {
        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create('app_user', [
                    ModelField::id(),
                    ModelField::string('email', 200)->notNull()->unique(),
                    ModelField::string('password_hash', 100)->notNull(),
                    ModelField::boolean('active')->default(true),
                ]);

                $plan->create('agency', [
                    ModelField::id(),
                    ModelField::string('name', 100),
                    ModelField::boolean('active')->default(true),
                ]);

                $plan->create('user_agency', [
                    ModelField::integer('app_user')->references('app_user', 'id'),
                    ModelField::integer('agency')->references('agency', 'id'),
                ]);
                $plan->addUniqueIndex('user_agency', ['app_user', 'agency']);
            }
        };

        $plan = $this->getDatabasePlan($database);
        $thrown = $migration->execute($plan, $database);

        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');

        $this->assertTrue($database->hasTable("agency"));
        $this->assertTrue($database->hasField("agency", "id"));
        $this->assertTrue($database->hasField("agency", "name"));
        $this->assertTrue($database->hasField("agency", "active"));

        $this->assertTrue($database->hasTable("user_agency"));
        $this->assertTrue($database->hasField("user_agency", "app_user"));
        $this->assertTrue($database->hasField("user_agency", "agency"));

    }

    /**
     * MySQL concatenated the quoted default into a template holding placeholders : a `{}` inside
     * the default was taken for one of them
     */
    #[ DataProvider('getDatabases') ]
    public function testAlterColumnKeepsADefaultHoldingBraces(Database $database)
    {
        if ('sqlite' === $database->getDriver())
            $this->markTestSkipped('SQLite cannot alter a column');

        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create('setting', [
                    ModelField::id(),
                    ModelField::string('value', 255),
                ]);
                $plan->alterColumn('setting', 'value', ModelField::string('value', 255)->default('{}'));
            }
        };

        $thrown = $migration->execute($this->getDatabasePlan($database), $database);
        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');

        $database->exec('INSERT INTO setting (id) VALUES (1)');
        $this->assertEquals('{}', $database->query('SELECT value FROM setting')[0]['value']);
    }

    /**
     * PostgreSQL has no DATETIME type, create() maps it to TIMESTAMP but alterColumn() does not
     */
    #[ DataProvider('getDatabases') ]
    public function testAlterColumnToDatetime(Database $database)
    {
        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create('delivery', [
                    ModelField::id(),
                    ModelField::date('delivered_at'),
                ]);
                $plan->alterColumn('delivery', 'delivered_at', ModelField::datetime('delivered_at'));
            }
        };

        $thrown = $migration->execute($this->getDatabasePlan($database), $database);

        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');
    }

    /**
     * The PostgreSQL plan sends an `ALTER COLUMN` without any action for a unique field
     */
    #[ DataProvider('getDatabases') ]
    public function testAlterColumnToUnique(Database $database)
    {
        $migration = new class extends Migration {
            public function up(Plan $plan, Database $database)
            {
                $plan->create('coupon', [
                    ModelField::id(),
                    ModelField::string('code', 50),
                ]);
                $plan->alterColumn('coupon', 'code', ModelField::string('code', 50)->unique());
            }
        };

        $thrown = $migration->execute($this->getDatabasePlan($database), $database);

        $this->assertNull($thrown, $thrown?->getMessage() ?? 'Nothing thrown');
    }

    /** The SQLite plan only logged a warning, although SQLite creates an index on an existing table */
    #[ DataProvider('getDatabases') ]
    public function testAddUniqueIndexRefusesDuplicates(Database $database)
    {
        $plan = $this->getDatabasePlan($database);
        $plan->create('user_agency', [
            ModelField::integer('app_user'),
            ModelField::integer('agency'),
        ]);
        $plan->addUniqueIndex('user_agency', ['app_user', 'agency']);

        $database->exec('INSERT INTO user_agency (app_user, agency) VALUES (1, 1)');

        $this->expectException(\PDOException::class);
        $database->exec('INSERT INTO user_agency (app_user, agency) VALUES (1, 1)');
    }

    /** The SQLite plan wrote its foreign keys without their ON DELETE clause */
    #[ DataProvider('getDatabases') ]
    public function testForeignKeysApplyTheirDeleteBehavior(Database $database)
    {
        $plan = $this->getDatabasePlan($database);
        $plan->create('customer', [ModelField::id()]);
        $plan->create('customer_order', [
            ModelField::id(),
            ModelField::integer('customer')->references('customer', 'id')->notNull()->onDeleteCascade(),
        ]);
        $plan->create('customer_note', [
            ModelField::id(),
            ModelField::integer('customer')->references('customer', 'id')->onDeleteSetNull(),
        ]);

        $database->exec('INSERT INTO customer (id) VALUES (1)');
        $database->exec('INSERT INTO customer_order (id, customer) VALUES (1, 1)');
        $database->exec('INSERT INTO customer_note (id, customer) VALUES (1, 1)');
        $database->exec('DELETE FROM customer WHERE id = 1');

        $this->assertCount(0, $database->query('SELECT id FROM customer_order'));
        $this->assertNull($database->query('SELECT customer FROM customer_note')[0]['customer']);
    }
}
