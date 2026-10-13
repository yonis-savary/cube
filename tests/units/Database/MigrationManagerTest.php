<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader\Applications;
use Cube\Data\Database\Migration\Adapters\SQLite;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Env\Configuration;
use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use Cube\Tests\Units\Env\Classes\HasTemporaryStorage;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class MigrationManagerTest extends TestCase
{
    use HasTemporaryStorage;

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
                fn () => new SQLite((new SQLiteProvider())->getEmptyDatabase(), new MigrationManagerConfiguration())
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
                fn () => new SQLite((new SQLiteProvider())->getEmptyDatabase(), new MigrationManagerConfiguration())
            );
            $written = $manager->createMigration('CreateInvoice', $this->storage);

            $this->assertStringStartsWith(date('Y_m_d_H_'), basename($written));
        } finally {
            $this->tearDownTemporaryStorage();
        }
    }
}
