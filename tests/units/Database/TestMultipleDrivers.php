<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;

/**
 * Runs a test class against every available DBMS.
 *
 * Each test method receives its own empty database as a parameter. Providers register
 * what they create, and `DatabaseProvider::dropPendingDatabases()` drops the lot when
 * the process ends.
 */
trait TestMultipleDrivers
{
    /** @return array<string,array{Database}> */
    public static function getDatabases(): array
    {
        return Bunch::of(Autoloader::classesThatExtends(DatabaseProvider::class))
            ->instanciates()
            ->map(fn (DatabaseProvider $provider) => $provider->getEmptyDatabase())
            ->map(fn (Database $database) => [$database->getDriver(), [$database]])
            ->zip()
        ;
    }
}
