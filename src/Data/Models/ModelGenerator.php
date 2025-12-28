<?php

namespace Cube\Data\Models;

use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Env\Storage;
use Cube\Data\Models\ModelGenerator\Adapters\DatabaseAdapter;
use Cube\Utils\Path;

class ModelGenerator
{
    use Component;

    protected Database $database;

    public function getAdapter(Database $database): DatabaseAdapter
    {
        $driver = $database->getDriver();
        $adapter = Bunch::fromExtends(DatabaseAdapter::class, [$database])
            ->first(fn (DatabaseAdapter $x) => $x->supports($driver))
        ;

        if (!$adapter) {
            throw new \Exception("Could not find adapter for [{$driver}] database");
        }
        return $adapter;
    }

    public function processDatabase(Database $database, Storage $destination, ?string $forceNamespace = null): array
    {
        $adapter = $this->getAdapter($database);

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
