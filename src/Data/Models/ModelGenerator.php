<?php

namespace Cube\Data\Models;

use Cube\Core\Component;
use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Env\Storage;
use Cube\Data\Models\ModelGenerator\Adapters\DatabaseAdapter;
use Cube\Data\Models\ModelGenerator\Table;
use Cube\Utils\Path;

class ModelGenerator
{
    use Component;

    protected Database $database;

    public function getAdapter(Database $database): DatabaseAdapter
    {
        $driver = $database->getDriver();
        $adapter = Bunch::fromExtends(DatabaseAdapter::class, [$database])
            ->first(fn($x) => $x->supports($driver))
        ;

        if (!$adapter) {
            throw new \Exception("Could not find adapter for [{$driver}] database");
        }
        return $adapter;
    }

    public function processDatabase(Database $database, Storage $destination, ?string $namespace = null): array
    {
        $adapter = $this->getAdapter($database);
        $adapter->process();

        $namespace ??= Path::pathToNamespace($destination->getRoot());
        $relations = $adapter->getRelations();

        return Bunch::of($adapter->getTables())
            ->map(fn($table) => $table->generateInto($destination, $namespace, $relations))
            ->get();
    }
}
