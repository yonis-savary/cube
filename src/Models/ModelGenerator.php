<?php

namespace Cube\Models;

use Cube\Core\Autoloader;
use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Env\Storage;
use Cube\Models\ModelGenerator\Adapters\DatabaseAdapter;
use Cube\Utils\Path;

class ModelGenerator
{
    use Component;

    protected Database $database;

    public function processDatabase(Database $database, Storage $destination, ?string $forceNamespace = null): array
    {
        $driver = $database->getDriver();

        $adapter = Bunch::of(Autoloader::classesThatExtends(DatabaseAdapter::class))
            ->map(fn ($x) => new $x($database))
            ->first(fn (DatabaseAdapter $x) => Bunch::of($x->getSupportedDriver())->has($driver))
        ;

        if (!$adapter) {
            throw new \Exception("Could not find adapter for [{$driver}] database");
        }

        // @var DatabaseAdapter $adapter
        $adapter->process();

        $namespace = $forceNamespace ?? Path::pathToNamespace($destination->getRoot());

        $files = [];

        $relations = $adapter->getRelations();
        foreach ($adapter->getTables() as $table) {
            $files[] = $table->generateInto($destination, $namespace, $relations);
        }

        return $files;
    }
}
