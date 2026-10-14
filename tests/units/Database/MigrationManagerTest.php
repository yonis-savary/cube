<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader\Applications;
use Cube\Data\Database\Database;
use Cube\Data\Database\MigrationManager;
use Cube\Data\Database\Migration\FailedMigrationException;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Env\Configuration;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MigrationManagerTest extends TestCase
{
    use HasTemporaryStorage;
    use TestMultipleDrivers;

    /** Pending migrations were filtered by their full path, so an applied one was never recognized as such. */
    public function testAnAppliedMigrationIsNotPendingAnymore()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $this->storage->child('Migrations')->write('2026_01_01_create_invoice.php', '<?php
                use Cube\Data\Database\Database;
                use Cube\Data\Database\Migration\Migration;
                use Cube\Data\Database\Migration\Plan;
                use Cube\Data\Models\ModelField;

                return new class extends Migration {
                    public function up(Plan $plan, Database $database) {
                        $plan->create("invoice", [ModelField::id()]);
                    }
                };
            ');

            $manager = Configuration::withInstance(
                new Configuration(new Applications($this->storage->getRoot())),
                fn () => new MigrationManager((new SQLiteProvider())->getEmptyDatabase(), new MigrationManagerConfiguration())
            );

            $logs = [];
            $manager->setLoggingFunction(function (string $message) use (&$logs) { $logs[] = $message; });

            $manager->executeAllMigrations();
            $logs = [];
            $manager->executeAllMigrations();

            $this->assertStringContainsString('Nothing to migrate', join("\n", $logs));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    /** Names used a 12-hour clock, so a migration made at 1 PM sorted before one made at 11 AM the same day. */
    public function testAMigrationNameUsesA24HourClock()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $manager = Configuration::withInstance(
                new Configuration(new Applications($this->storage->getRoot())),
                fn () => new MigrationManager((new SQLiteProvider())->getEmptyDatabase(), new MigrationManagerConfiguration())
            );
            $written = $manager->createMigration('CreateInvoice', $this->storage);

            $this->assertStringStartsWith(date('Y_m_d_H_'), basename($written));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    /** Migration::down() was called by no command : an applied migration could not be reverted. */
    public function testRollbackRevertsTheLastAppliedMigrationOnly()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $this->writeMigration('2026_01_01_create_invoice.php', '$plan->create("invoice", [ModelField::id()]);', '$plan->dropTable("invoice");');
            $this->writeMigration('2026_01_02_create_customer.php', '$plan->create("customer", [ModelField::id()]);', '$plan->dropTable("customer");');

            $database = (new SQLiteProvider())->getEmptyDatabase();
            $manager = $this->makeManager($database);
            $manager->executeAllMigrations();

            $manager->rollback();

            $this->assertFalse($database->hasTable('customer'));
            $this->assertTrue($database->hasTable('invoice'));
            $this->assertEquals(['2026_01_01_create_invoice.php'], $manager->listDoneMigrations());

            $manager->executeAllMigrations();
            $this->assertTrue($database->hasTable('customer'));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    /** Reverting in application order would drop a table before the migration that altered it is reverted. */
    public function testRollbackRevertsSeveralMigrationsNewestFirst()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $this->writeMigration('2026_01_01_create_invoice.php', '$plan->create("invoice", [ModelField::id()]);', '$plan->dropTable("invoice");');
            $this->writeMigration('2026_01_02_add_invoice_total.php', '$plan->addColumn("invoice", ModelField::integer("total"));', '$plan->dropColumn("invoice", "total");');

            $database = (new SQLiteProvider())->getEmptyDatabase();
            $manager = $this->makeManager($database);
            $manager->executeAllMigrations();

            $manager->rollback(2);

            $this->assertFalse($database->hasTable('invoice'));
            $this->assertEquals([], $manager->listDoneMigrations());

            $logs = [];
            $manager->setLoggingFunction(function (string $message) use (&$logs) { $logs[] = $message; });
            $manager->rollback();
            $this->assertStringContainsString('Nothing to rollback', join("\n", $logs));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    /** A failed rollback must leave the migration recorded as applied. */
    public function testAFailedRollbackKeepsTheMigrationApplied()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $this->writeMigration('2026_01_01_create_invoice.php', '$plan->create("invoice", [ModelField::id()]);', '$plan->dropTable("not_a_table");');

            $database = (new SQLiteProvider())->getEmptyDatabase();
            $manager = $this->makeManager($database);
            $manager->executeAllMigrations();

            try {
                $manager->rollback();
                $this->fail('Dropping an unknown table cannot be rolled back');
            } catch (FailedMigrationException) {
            }

            $this->assertTrue($database->hasTable('invoice'));
            $this->assertEquals(['2026_01_01_create_invoice.php'], $manager->listDoneMigrations());
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    /** dryRun() only caught DryRunPlanException : anything else thrown by up() escaped executeMigration()'s error handling. */
    public function testAnyErrorDuringTheDryRunFailsTheMigration()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $file = $this->writeMigration('2026_01_01_broken.php', 'throw new \\RuntimeException("broken migration");', '');

            $database = (new SQLiteProvider())->getEmptyDatabase();
            $manager = $this->makeManager($database);

            $this->assertFalse($manager->executeMigration($file));
            $this->assertEquals('broken migration', $manager->getLastError()->getMessage());
            $this->assertEquals([], $manager->listDoneMigrations());
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    protected function writeMigration(string $name, string $up, string $down): string
    {
        $migrations = $this->storage->child('Migrations');
        $migrations->write($name, '<?php
            use Cube\Data\Database\Database;
            use Cube\Data\Database\Migration\Migration;
            use Cube\Data\Database\Migration\Plan;
            use Cube\Data\Models\ModelField;

            return new class extends Migration {
                public function up(Plan $plan, Database $database) { '.$up.' }
                public function down(Plan $plan, Database $database) { '.$down.' }
            };
        ');

        return $migrations->path($name);
    }

    #[DataProvider('getDatabases')]
    public function testAppliedMigrationsAreTrackedOnEveryDriver(Database $database)
    {
        $this->setUpTemporaryStorage('migration-manager-test-');

        try {
            $this->assertMigrationsAreTracked($this->makeManager($database));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    protected function assertMigrationsAreTracked(MigrationManager $manager): void
    {
        $manager->createMigrationTableIfInexistant();
        $manager->createMigrationTableIfInexistant();

        $manager->markMigrationAsDone('2026_01_01_create_invoice.php');
        $manager->markMigrationAsDone('2026_01_02_create_customer.php');

        $this->assertTrue($manager->migrationWasMade('2026_01_01_create_invoice.php'));
        $this->assertFalse($manager->migrationWasMade('2026_01_03_create_product.php'));
        $this->assertSame(['2026_01_01_create_invoice.php', '2026_01_02_create_customer.php'], $manager->listDoneMigrations());

        $manager->markMigrationAsUndone('2026_01_01_create_invoice.php');

        $this->assertFalse($manager->migrationWasMade('2026_01_01_create_invoice.php'));
        $this->assertSame(['2026_01_02_create_customer.php'], $manager->listDoneMigrations());
    }

    public function testTheDefaultInstanceUsesTheGlobalDatabase()
    {
        $this->setUpTemporaryStorage('migration-manager-test-');
        $database = (new SQLiteProvider())->getEmptyDatabase();

        try {
            $manager = Configuration::withInstance(
                new Configuration(new Applications($this->storage->getRoot())),
                fn () => $database->asGlobalInstance(fn () => MigrationManager::withInstance(null, fn () => MigrationManager::getInstance()))
            );

            $manager->createMigrationTableIfInexistant();
            $this->assertTrue($database->hasTable($manager->getMigrationTableName()));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }

    protected function makeManager(Database $database): MigrationManager
    {
        return Configuration::withInstance(
            new Configuration(new Applications($this->storage->getRoot())),
            fn () => new MigrationManager($database, new MigrationManagerConfiguration())
        );
    }
}
