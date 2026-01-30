<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;

trait TestMultipleDrivers
{
    /** @return Database[] */
    public static function getDatabases(): array
    {
        return Bunch::of(Autoloader::classesThatExtends(DatabaseProvider::class))
            ->instanciates()
            ->map(fn (DatabaseProvider $providerClass) => $providerClass->getEmptyDatabase())
            ->map(fn(Database $database) => [$database->getDriver() , [$database]])
            ->zip()
        ;
    }
}
