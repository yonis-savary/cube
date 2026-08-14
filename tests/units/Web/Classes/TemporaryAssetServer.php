<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Data\Bunch;
use Cube\Env\Storage;
use Cube\Web\Helpers\AssetServer;

/**
 * Serves the assets of a given directory instead of the ones the Autoloader discovered.
 */
class TemporaryAssetServer extends AssetServer
{
    protected static ?Storage $assetsStorage = null;

    public static function servingFrom(Storage $storage): void
    {
        self::$assetsStorage = $storage;
    }

    protected static function findAssetFile(string $target): ?string
    {
        return Bunch::of(array_values(self::$assetsStorage->exploreFiles()))
            ->first(fn (string $file) => str_ends_with($file, $target));
    }
}
