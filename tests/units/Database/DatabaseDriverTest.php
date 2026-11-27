<?php

namespace Cube\Tests\Units\Database;

use Cube\Data\Database\Database;
use Cube\Data\Database\DatabaseConfiguration;
use Cube\Data\Database\DatabaseDriver;
use Cube\Env\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DatabaseDriverTest extends TestCase
{
    public function testADriverIsGivenAsAnEnumOrByItsPdoName()
    {
        $this->assertEquals('sqlite', (new Database(DatabaseDriver::SQLite))->getDriver());
        $this->assertEquals('sqlite', (new Database('sqlite'))->getDriver());
    }

    /** getDriver() promised a trimmed, lowercase name, and the migration plans lowercased it again to be sure. */
    public function testADriverNameIsNormalized()
    {
        $this->assertEquals('sqlite', (new Database(' SQLite '))->getDriver());
    }

    public function testTheConfigurationTakesAnEnum()
    {
        $database = (new Configuration(new DatabaseConfiguration(DatabaseDriver::SQLite, '')))->asGlobalInstance(
            fn () => Database::getDefaultInstance()
        );

        $this->assertEquals(DatabaseDriver::SQLite->value, $database->getDriver());
    }

    public function testAConfigurationHoldingAnEnumCanBeCached()
    {
        $configuration = unserialize(serialize(new DatabaseConfiguration(DatabaseDriver::MySQL)));

        $this->assertSame(DatabaseDriver::MySQL, $configuration->driver);
    }
}
