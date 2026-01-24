<?php

namespace Cube\Tests\Integration;

use Cube\Data\Bunch;
use Cube\Data\Database\Database;
use Cube\Data\Database\Migration\Migration;
use Cube\Data\Database\Migration\MigrationManagerConfiguration;
use Cube\Data\Database\Migration\Plans\SQLite;
use Cube\Env\Storage;
use Cube\Env\Logger\Logger;
use Cube\Utils\Path;
use Cube\Utils\Shell;
use Cube\Web\Helpers\CubeServer;

class Utils
{
    protected static ?Storage $storage = null;
    protected static ?CubeServer $server = null;

    public static function getIntegrationAppStorage(): Storage
    {
        return new Storage(Path::normalize(__DIR__.'/../integration/apps'));
    }

    public static function getDummyApplicationStorage(): Storage
    {
        if (self::$storage) {
            return self::$storage;
        }

        $logger = Logger::getInstance();

        $integrationApp = self::getIntegrationAppStorage();

        $storage = $integrationApp->child(uniqid('App'));
        $storage->makeDirectory('Storage');

        $cubeRoot = realpath(Path::normalize(__DIR__.'/../..'));

        $integrationBaseFiles = (new Storage($cubeRoot))->child('tests/integration-root')->getRoot();

        $logger->info('Made integration app at {path}', ['path' => Path::toRelative($integrationBaseFiles)]);

        $storage->write('composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'App',
                    'Tests\\' => 'Tests',
                ],
            ],
            'require' => [
                'yonis-savary/cube' => 'dev-main',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '11.5.3',
            ],
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => $cubeRoot,
                    'options' => [
                        'symlink' => true,
                    ],
                ],
            ],

            'scripts' => [
                'post-update-cmd' => [
                    'cp -r vendor/yonis-savary/cube/server/* .',
                    "cp -r {$integrationBaseFiles}/* .",
                    "cp -r {$integrationBaseFiles}/.env .",
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $installProcess = Shell::executeInDirectory('composer install', $storage->getRoot());
        $migrateProcess = Shell::executeInDirectory('php do migrate', $storage->getRoot());

        if (!$storage->isFile('do')) {
            trigger_error('Could not install integration app at '.$storage->getRoot().' => '.$installProcess->getOutput());
        }

        if (!$storage->isFile('App/Models/User.php')) {
            trigger_error('Could not generate models in integration app at '.$storage->getRoot().' => '.$migrateProcess->getOutput());
        }

        return self::$storage = $storage;
    }
}
