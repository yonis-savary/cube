<?php

namespace Cube\Tests\Units\Database;

use Cube\Tests\Units\Database\Providers\SQLiteProvider;
use PHPUnit\Framework\TestCase;

class DatabaseProviderTest extends TestCase
{
    public function testProvidedDatabasesAreRecognizedAsRandom()
    {
        $database = (new SQLiteProvider())->getEmptyDatabase();

        $this->assertTrue(DatabaseProvider::isRandomDatabaseName(basename($database->getDatabase())));
    }

    /** The leftover sweep runs against the whole server : nothing else may look like a test database. */
    public function testServerDatabasesAreNotRecognizedAsRandom()
    {
        foreach (['mysql', 'sys', 'information_schema', 'performance_schema', 'postgres', 'template0', 'template1'] as $name)
            $this->assertFalse(DatabaseProvider::isRandomDatabaseName($name), $name);
    }
}
