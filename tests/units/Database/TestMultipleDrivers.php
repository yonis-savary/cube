<?php

namespace Cube\Tests\Units\Database;

use Cube\Core\Autoloader;
use Cube\Data\Bunch;
use Cube\Database\Database;

use function Cube\debug;

trait TestMultipleDrivers
{
    /** @return Database[] */
    public static function getDatabases(): array
    {
        return Bunch::of(Autoloader::classesThatImplements(DatabaseProvider::class))
            ->map(fn($providerClass) => [$providerClass::getDatabase()])
            ->toArray();
    }
}