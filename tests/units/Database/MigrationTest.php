<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\Plan;
use Cube\Data\Database\Migration\Plans\Exceptions\DryRunPlanException;
use Cube\Data\Models\ModelField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MigrationTest extends TestCase
{
    use TestMultipleDrivers;
    protected function getDatabasePlan(Database $database): Plan
    {
        $databaseDriver = $database->getDriver();
        $plan = Bunch::fromExtends(Plan::class, [$database])
            ->first(fn($p) => $p->support($databaseDriver));

        if ($plan)
            return $plan;

        throw new RuntimeException("Could not find any Plan class for database of type $databaseDriver");
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
            }
        };

        $plan = $this->getDatabasePlan($database);
        $migration->execute($plan, $database);

        $this->assertTrue($database->hasTable("my_users"));
        $this->assertTrue($database->hasField("my_users", "username"));
        $this->assertTrue($database->hasField("my_users", "password"));
        $this->assertTrue($database->hasField("my_users", "bio"));
        $this->assertTrue($database->hasField("my_users", "last_login"));
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
}