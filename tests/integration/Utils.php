<?php

namespace YonisSavary\Cube\Tests\Integration;

use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Utils\Path;

class Utils
{
    public static function getIntegrationAppStorage(): Storage
    {
        return new Storage(Path::normalize(__DIR__ . "/../integration-apps"));
    }

    public static function getDummyApplicationStorage(): Storage
    {
        $integrationApp = self::getIntegrationAppStorage();
        $storage = $integrationApp->child(uniqid("App"));

        return $storage;
    }

    public static function removeApplicationStorage(Storage $app): void
    {
        $integrationAppHolder = self::getIntegrationAppStorage();
        if (!str_starts_with($app->getRoot(), $integrationAppHolder->getRoot()))
            return;

        foreach (array_reverse($app->exploreFiles()) as $file)
            unlink($file);

        foreach (array_reverse($app->exploreDirectories()) as $directory)
            rmdir($directory);

        rmdir($app->getRoot());
    }
}