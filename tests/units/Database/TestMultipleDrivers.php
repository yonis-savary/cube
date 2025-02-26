<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Database\Database;

trait TestMultipleDrivers
{
    /** @return Database[] */
    public static function getDatabases(): array
    {
        return Bunch::of(Autoloader::classesThatExtends(DatabaseProvider::class))
            ->map(fn ($providerClass) => new $providerClass())
            ->map(fn (DatabaseProvider $providerClass) => [$providerClass->getEmptyDatabase()])
            ->toArray()
        ;
    }
}
