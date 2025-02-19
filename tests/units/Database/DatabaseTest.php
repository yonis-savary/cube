<?php

namespace Cube\Tests\Units\Database;

use Cube\Database\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    use TestMultipleDrivers;

    #[ DataProvider('getDatabases') ]
    public function testBase(Database $database)
    {
        $this->assertNotNull(
            $database->query("SELECT 1")
        );
    }
}