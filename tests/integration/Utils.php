<?php

namespace Cube\Tests\Integration;

use Cube\Data\Bunch;
use Cube\Database\Database;
use Cube\Database\Migration\Migration;
use Cube\Env\Storage;
use Cube\Logger\Logger;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use Cube\Web\CubeServer;

class Utils
{
    protected static ?Storage $storage = null;
    protected static ?CubeServer $server = null;

    public static function getIntegrationAppStorage(): Storage
    {
        return new Storage(Path::normalize(__DIR__ . "/../integration-apps"));
    }

    public static function getIntegrationDatabase(): Database
    {
        $cubeRoot = Path::normalize(__DIR__ . "/../..");

        $datbase = new Database();

        $migrationsFiles = (new Storage($cubeRoot))->child("tests/root/App/Migrations")->files();
        Bunch::of($migrationsFiles)
        ->map(fn(string $file) => include $file)
        ->forEach(fn(Migration $migration) => $datbase->exec($migration->install));

        return $datbase;
    }

    public static function getDummyServer(): CubeServer
    {
        if (self::$server)
            return self::$server;

        $installation = self::getDummyApplicationStorage();
        return self::$server = new CubeServer(null, $installation->path("Public"), Logger::getInstance());
    }

    public static function getDummyApplicationStorage(): Storage
    {
        if (self::$storage)
            return self::$storage;

        $logger = Logger::getInstance();

        $integrationApp = self::getIntegrationAppStorage();

        $storage = $integrationApp->child(uniqid("App"));
        $storage->makeDirectory("Storage");

        $cubeRoot = Path::normalize(__DIR__ . "/../..");

        $integrationBaseFiles = (new Storage($cubeRoot))->child("tests/root")->getRoot();

        $logger->info('Made integration app at {path}', ['path' => Path::toRelative($integrationBaseFiles)]);

        $storage->write("composer.json", json_encode([
            "autoload" => [
                "psr-4" => [
                    "App\\" => "App"
                ]
            ],
            "require" => [
                "yonis-savary/cube" => "dev-main"
            ],
            "repositories" => [
                [
                    "type" => "path",
                    "url" => $cubeRoot,
                    "options" => [
                        "symlink" => true
                    ]
                ]
                ],

                "scripts" => [
                    "post-update-cmd" => [
                        "cp -r $integrationBaseFiles/* .",
                        "cp -r $integrationBaseFiles/.env .",
                        "cp -r vendor/yonis-savary/cube/server/* ."
                    ]
                ]
        ], JSON_PRETTY_PRINT));

        $installProcess = Shell::executeInDirectory("composer install", $storage->getRoot());
        $migrateProcess = Shell::executeInDirectory("php do migrate", $storage->getRoot());

        if (!$storage->isFile("do"))
            trigger_error("Could not install integration app at " . $storage->getRoot(). " => " . $installProcess->getOutput());

        if (!$storage->isFile("App/Models/User.php"))
            trigger_error("Could not generate models in integration app at " . $storage->getRoot() . " => " . $migrateProcess->getOutput());

        return self::$storage = $storage;
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