<?php

namespace YonisSavary\Cube\Models;

use Exception;
use YonisSavary\Cube\Core\Autoloader;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Data\Bunch;
use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Models\ModelGenerator\Adapters\AbstractDatabaseAdapter;
use YonisSavary\Cube\Utils\Path;

class ModelGenerator
{
    use Component;

    protected Database $database;

    public function processDatabase(Database $database, Storage $destination)
    {
        $driver = $database->getDriver();

        $adapter = Bunch::of(Autoloader::classesThatExtends(AbstractDatabaseAdapter::class))
        ->map(fn($x) => new $x($database))
        ->first(fn(AbstractDatabaseAdapter $x) => Bunch::of($x->getSupportedDriver())->has($driver));

        if (!$adapter)
            throw new Exception("Could not find adapter for [$driver] database");

        /** @var AbstractDatabaseAdapter $adapter */
        $adapter->process();

        $namespace = Path::pathToNamespace($destination->getRoot());

        $constraints = $adapter->getConstraints();
        foreach($adapter->getTables() as $table)
            $table->generateInto($destination, $namespace, $constraints);
    }
}